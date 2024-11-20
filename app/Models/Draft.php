<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Draft extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'from', 'to', 'cc', 'subject', 'message', 'attachments',
    ];

    protected $casts = [
        'attachments' => 'array',
    ];
}