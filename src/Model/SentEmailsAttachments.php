<?php

namespace jdavidbakr\MailTracker\Model;

use Illuminate\Database\Eloquent\Model;

class SentEmailsAttachments extends Model
{
    protected $fillable = [
        'path'
    ];

    public $timestamps  = true;

    public function email()
    {
        return $this->belongsTo(SentEmail::class);
    }

    public function getAbsolutePathAttribute()
    {
        $attachments_dir = config('mail-tracker.attachments-path-storage');

        if (!ends_with($attachments_dir, '/')) {
            $attachments_dir .= '/';
        }

        $abslute_path = $attachments_dir . $this->path;
        if (file_exists($abslute_path)) {
            return $abslute_path;
        }

        return storage_path('app/public/' . $this->path);
    }
}
