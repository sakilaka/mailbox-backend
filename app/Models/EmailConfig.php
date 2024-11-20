<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'driver',
        'host',
        'outgoing_port',
        'incoming_port',
        'username',
        'password',
        'encryption',
        'from_address',
        'from_name'
    ];
}