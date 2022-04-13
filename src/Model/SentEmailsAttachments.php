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

}
