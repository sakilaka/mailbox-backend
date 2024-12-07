<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function allUser(){
        $users = User::count();
        return response()->json([
            'total_user' => $users
        ]);
    }
    
    public function userList(){
        $users = User::all();
        return response()->json([
            'users' => $users
        ]);
    }
    
    public function allEmail(){
        $Emails = Email::count();
        return response()->json([
            'total_email' => $Emails
        ]);
    }
}