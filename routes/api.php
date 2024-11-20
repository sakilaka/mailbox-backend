<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmailConfigController;
use App\Http\Controllers\EmailController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
Route::post('/verify-token', [AuthController::class, 'verifyToken']);



Route::post('/email-config', [EmailConfigController::class, 'store'])->middleware('auth:api');

Route::get('/emails', [EmailController::class, 'index']);
Route::get('/sent-emails', [EmailController::class, 'sentEmails']);
Route::get('/emailsTrash', [EmailController::class, 'indexTrash']);
Route::get('/emailsStarred', [EmailController::class, 'indexStarred']);
Route::get('/emailsArchive', [EmailController::class, 'indexArchive']);
Route::get('/emails/{id}', [EmailController::class, 'fetchEmailById']);
Route::get('/sentEmails/{id}', [EmailController::class, 'fetchSendEmailById']);


Route::post('/send-email', [EmailController::class, 'sendEmail']);
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
    