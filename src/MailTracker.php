<?php

namespace jdavidbakr\MailTracker;

use jdavidbakr\MailTracker\Model\SentEmail;
use jdavidbakr\MailTracker\Model\SentEmailUrlClicked;
use jdavidbakr\MailTracker\Events\EmailSentEvent;
use Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use jdavidbakr\MailTracker\Model\SentEmailsAttachments;

class MailTracker implements \Swift_Events_SendListener {

	protected $hash;

	/**
	 * Inject the tracking code into the message
	 */
	public function beforeSendPerformed(\Swift_Events_SendEvent $event)
	{
		$message = $event->getMessage();

        // Create the trackers
        $this->createTrackers($message);

    	// Purge old records
        $this->purgeOldRecords();
	}

    public function sendPerformed(\Swift_Events_SendEvent $event)
    {
        // If this was sent through SES, retrieve the data
        if(config('mail.driver') == 'ses') {
            $message = $event->getMessage();
            $this->updateSesMessageId($message);
        }
    }

    protected function updateSesMessageId($message)
    {
        // Get the SentEmail object
        $headers = $message->getHeaders();
        $hash = $headers->get('X-Mailer-Hash')->getFieldBody();
        $sent_email = SentEmail::where('hash',$hash)->first();

        // Get info about the
        $sent_email->message_id = $headers->get('X-SES-Message-ID')->getFieldBody();
        $sent_email->save();
    }

    protected function addTrackers($html, $hash)
    {
    	if(config('mail-tracker.inject-pixel')) {
	    	$html = $this->injectTrackingPixel($html, $hash);
    	}
    	if(config('mail-tracker.track-links')) {
    		$html = $this->injectLinkTracker($html, $hash);
    	}

    	return $html;
    }

    protected function injectTrackingPixel($html, $hash)
    {
    	// Append the tracking url
    	$tracking_pixel = '<img src="'.route('mailTracker_t',[$hash]).'" />';

    	$linebreak = str_random(32);
    	$html = str_replace("\n",$linebreak,$html);

    	if(preg_match("/^(.*<body[^>]*>)(.*)$/", $html, $matches)) {
    		$html = $matches[1].$tracking_pixel.$matches[2];
    	} else {
    		$html = $html . $tracking_pixel;
    	}
    	$html = str_replace($linebreak,"\n",$html);

    	return $html;
    }

    protected function injectLinkTracker($html, $hash)
    {
    	$this->hash = $hash;

    	$html = preg_replace_callback("/(<a[^>]*href=['\"])([^'\"]*)/",
    			array($this, 'inject_link_callback'),
    			$html);

    	return $html;
    }

    protected function inject_link_callback($matches)
    {
        if (empty($matches[2])) {
            $url = app()->make('url')->to('/');
        } else {
            $url = $matches[2];
        }

    	return $matches[1].route('mailTracker_l',
    		[
    			MailTracker::hash_url($url),
    			$this->hash
    		]);
    }

    static public function hash_url($url)
    {
        // Replace "/" with "$"
        return str_replace("/","$",base64_encode($url));
    }

    /**
     * Create the trackers
     *
     * @param  Swift_Mime_Message $message
     * @return void
     */
    protected function createTrackers($message)
    {
        foreach($message->getTo() as $to_email=>$to_name) {
            foreach($message->getFrom() as $from_email=>$from_name) {
                $headers = $message->getHeaders();
                $hash = str_random(32);
                $headers->addTextHeader('X-Mailer-Hash',$hash);
                $subject = $message->getSubject();

                $original_content = $message->getBody();

                $attachments = [];

                if ($message->getContentType() === 'text/html' ||
                    ($message->getContentType() === 'multipart/alternative' && $message->getBody()) ||
                    ($message->getContentType() === 'multipart/mixed' && $message->getBody())
                ) {
                    $message->setBody($this->addTrackers($message->getBody(), $hash));
                }

                foreach ($message->getChildren() as $part) {
                    if (strpos($part->getContentType(), 'text/html') === 0) {
                        $converter->setHTML($part->getBody());
                        $part->setBody($this->addTrackers($message->getBody(), $hash));
                    }
                    if(config('mail-tracker.store-attachments') && $part->getFilename())
                    {
                     
                        $path = "attachments/" . md5(microtime(true)) . "/" . $part->getFilename();
                        if(Storage::put($path,$part->getBody()))
                            $attachments[] = new SentEmailsAttachments([
                                "path" => $path
                            ]);
                    }
                        
                }

                $tracker = SentEmail::create([
                    'hash'=>$hash,
                    'headers'=>$headers->toString(),
                    'sender_name'=>$from_name,
                    'sender_email'=>$from_email,
                    'recipient_name'=>$to_name,
                    'recipient_email'=>$to_email,
                    'subject'=>$subject,
                    'content'=>$original_content,
                    'opens'=>0,
                    'clicks'=>0,
                    'message_id'=>$message->getId(),
                    'meta'=>[],
                ]);
                if(config('mail-tracker.store-attachments'))
                    $tracker->attachments()->saveMany($attachments);



                Event::fire(new EmailSentEvent($tracker));
            }
        }
    }

    /**
     * Purge old records in the database
     *
     * @return void
     */
    protected function purgeOldRecords()
    {
        if(config('mail-tracker.expire-days') > 0) {
            $emails = SentEmail::where('created_at','<',\Carbon\Carbon::now()
                ->subDays(config('mail-tracker.expire-days')))
                ->select('id')
                ->get();
            //deleting all attachments    
            foreach($emails as $email)
            {
                foreach($email->attachments as $attachment)
                    Storage::delete($attachment->path);
            }

            SentEmailUrlClicked::whereIn('sent_email_id',$emails->pluck('id'))->delete();
            SentEmailsAttachments::whereIn('sent_email_id',$emails->pluck('id'))->delete();
            SentEmail::whereIn('id',$emails->pluck('id'))->delete();
        }
    }
}
