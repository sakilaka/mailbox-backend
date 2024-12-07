<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleEmail extends Model
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
        'sent_at',
        'attachment',
        'is_trash'
    ];
}