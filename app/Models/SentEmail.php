<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SentEmail extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'from',
        'to',
        'cc',
        'subject',
        'message',
        'snippet',
        'schedule_time',
        'attachment',
        'is_trash'
    ];
}