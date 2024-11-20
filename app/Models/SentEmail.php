<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SentEmail extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'from',
        'to',
        'cc',
        'subject',
        'message',
        'snippet',
        'attachment',
        'is_trash'
    ];
}