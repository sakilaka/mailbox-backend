<?php

use App\Http\Controllers\admin\AdminController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailConfigController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\MemberController;



Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/register', [AuthController::class, 'register']);
Route::post('/register-referral', [AuthController::class, 'registerReferral']);
Route::put('/user/update', [AuthController::class, 'update']);
Route::get('/user/details', [AuthController::class, 'getUserDetails']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
Route::post('/verify-token', [AuthController::class, 'verifyToken']);



Route::post('/email-config', [EmailConfigController::class, 'store'])->middleware('auth:api');

Route::get('/emails', [EmailController::class, 'index'])->middleware('auth:api');
Route::get('/sent-emails', [EmailController::class, 'sentEmails'])->middleware('auth:api');
Route::get('/emailsTrash', [EmailController::class, 'indexTrash']);
Route::get('/emailsStarred', [EmailController::class, 'indexStarred']);
Route::get('/emailsArchive', [EmailController::class, 'indexArchive']);
Route::get('/emails/{id}', [EmailController::class, 'fetchEmailById']);
Route::get('/sentEmails/{id}', [EmailController::class, 'fetchSendEmailById']);

Route::get('/all-config', [EmailController::class, 'allConfig'])->middleware('auth:api');

Route::post('/send-email', [EmailController::class, 'sendEmail'])->middleware('auth:api');
Route::post('/schedule-email', [EmailController::class, 'scheduleEmail'])->middleware('auth:api');

Route::get('/user-emails', [EmailController::class, 'getUserEmails']);

Route::post('/emails/reply', [EmailController::class, 'sendReply']);


Route::patch('/emails/{id}/sentTrash', [EmailController::class, 'sentMarkAsTrash']);
Route::delete('/emails/{id}/sentDelete', [EmailController::class, 'deleteEmail']);
Route::patch('/emails/{id}/sentRestore', [EmailController::class, 'restoreEmail']);


Route::patch('/emails/{id}/trash', [EmailController::class, 'markAsTrash']);
Route::patch('/emails/{id}/archive', [EmailController::class, 'markAsArchive']);
Route::delete('/emails/{id}/delete', [EmailController::class, 'deleteEmail']);


Route::patch('/emails/{id}/read', [EmailController::class, 'markAsRead']);
Route::patch('/emails/{id}/star', [EmailController::class, 'toggleStar']);


Route::post('/drafts', [EmailController::class, 'store'])->middleware('auth:api');
Route::get('/drafts', [EmailController::class, 'indexDraft'])->middleware('auth:api');
Route::delete('/drafts/{id}', [EmailController::class, 'destroy'])->middleware('auth:api');
Route::get('/drafts/{id}/attachments', [EmailController::class, 'getDraftAttachments'])->middleware('auth:api');

Route::get('/download/{path}', [EmailController::class, 'downloadAttachment'])->where('path', '.*');

Route::delete('/email-config/{id}', [EmailConfigController::class, 'destroy']);
Route::get('/email-config/{id}', [EmailConfigController::class, 'show']);
Route::put('/email-config/{id}', [EmailConfigController::class, 'update']);


Route::delete('emails/delete-all-trash', [EmailController::class, 'deleteAllTrashedEmails']);

Route::get('/members', [MemberController::class, 'index'])->middleware('auth:api');
Route::post('/members', [MemberController::class, 'store'])->middleware('auth:api');
Route::get('/members/{id}', [MemberController::class, 'show']);
Route::put('/members/{id}', [MemberController::class, 'update'])->middleware('auth:api');
Route::delete('/members/{id}', [MemberController::class, 'destroy']);

Route::patch('/emails/trash', [EmailController::class, 'moveEmailsToTrash']);


// admin 
Route::get('/all-user', [AdminController::class, 'allUser']);
Route::get('/all-email', [AdminController::class, 'allEmail']);
Route::get('/user-list', [AdminController::class, 'userList']);