<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_config_id',
        'message_id',
        'sender',
        'subject',
        'body',
        'content_type',
        'attachment',
        'snippet',
        'is_read',
        'is_trash',
        'is_archive',
        'is_starred',
    ];
}