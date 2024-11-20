<?php

namespace App\Http\Controllers;

use App\Models\Draft;
use App\Models\Email;
use App\Models\EmailConfig;
use App\Models\SentEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webklex\PHPIMAP\ClientManager;

class EmailController extends Controller
{

    public function sendEmail(Request $request)
    {

        
        try {
            $validated = $request->validate([
                'from'        => 'required|email',
                'to'          => 'required|email',
                'cc'          => 'nullable|email',
                'subject'     => 'required|string',
                'message'     => 'required|string',
                'attachments' => 'nullable|array', 
                'attachments.*' => 'file', 
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Validation failed.'], 422);
        }

        
        $emailData = [
            'from'    => $validated['from'],
            'to'      => $validated['to'],
            'cc'      => $validated['cc'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
        ];

        
        $emailConfig = EmailConfig::whereJsonContains('username', ['email' => $validated['from']])->first();
        if (!$emailConfig) {

            return response()->json(['error' => 'Email configuration not found for the given email address.'], 404);
        }

        
        $userAccounts    = json_decode($emailConfig->username, true);
        $selectedAccount = collect($userAccounts)->firstWhere('email', $validated['from']);

        if (!$selectedAccount) {

            return response()->json(['error' => 'No matching email account found for sending email.'], 404);
        }

        
        try {
            config([
                'mail.mailers.smtp.username'   => $selectedAccount['email'],
                'mail.mailers.smtp.password'   => $selectedAccount['password'],
                'mail.mailers.smtp.host'       => "smtp.hostinger.com",
                'mail.mailers.smtp.port'       => 465,
                'mail.mailers.smtp.encryption' => "tls",
            ]);

        } catch (\Exception $e) {

            return response()->json(['error' => 'Failed to configure mail settings.'], 500);
        }

        
        try {
            Mail::send([], [], function ($message) use ($emailData, $request) {
                $message->from($emailData['from'])
                    ->to($emailData['to'])
                    ->cc($emailData['cc'])
                    ->subject($emailData['subject'])
                    ->html($emailData['message']);

                
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        $message->attach($file->getRealPath(), [
                            'as'   => $file->getClientOriginalName(),
                            'mime' => $file->getMimeType(),
                        ]);
                    }
                }
            });

        } catch (\Exception $e) {

            return response()->json(['error' => 'Failed to send email.'], 500);
        }

        
        try {
            $sentEmail          = new SentEmail();
            $sentEmail->from    = $emailData['from'];
            $sentEmail->to      = $emailData['to'];
            $sentEmail->cc      = $emailData['cc'];
            $sentEmail->subject = $emailData['subject'];
            $sentEmail->message = $emailData['message'];

            
            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $filePath      = $file->store('attachments', 'public');
                    $attachments[] = $filePath;
                }
                $sentEmail->attachment = json_encode($attachments); 
            }

            $sentEmail->save();

        } catch (\Exception $e) {

            return response()->json(['error' => 'Failed to save email to database.'], 500);
        }

        return response()->json(['message' => 'Email sent and stored successfully']);
    }

    public function getUserEmails()
    {
        $emailConfig = EmailConfig::first();

        if ($emailConfig && $emailConfig->username) {
            $emails         = json_decode($emailConfig->username, true);
            $emailAddresses = array_column($emails, 'email');
            return response()->json(['emails' => $emailAddresses]);
        }

        return response()->json(['emails' => []]);
    }

    public function sentEmails()
    {
        $email = SentEmail::orderBy('created_at', 'desc')->where('is_trash', 0)->get();
        return response()->json([
            'email' => $email,
        ]);
    }

    public function index()
    {
        

        $emails = Email::query()
            ->where('is_trash', 0)
            ->where('is_archive', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'email' => $emails,
        ]);
    }

    public function indexTrash()
    {
        $email = Email::where('is_trash', 1)->orderBy('created_at', 'desc')->get();
        return response()->json([
            'email' => $email,
        ]);
    }

    public function indexStarred()
    {
        $email = Email::where('is_starred', 1)->orderBy('created_at', 'desc')->get();
        return response()->json([
            'email' => $email,
        ]);
    }

    public function indexArchive()
    {
        $email = Email::where('is_archive', 1)->orderBy('created_at', 'desc')->get();
        return response()->json([
            'email' => $email,
        ]);
    }

    public function fetchEmailById($id)
    {
        $email = Email::where('id', $id)->first();
        return response()->json([
            'email' => $email,
        ]);
    }

    public function fetchSendEmailById($id)
    {
        $email = SentEmail::where('id', $id)->first();
        return response()->json([
            'email' => $email,
        ]);
    }

    public function sendReply(Request $request)
    {
        $request->validate([
            'to'              => 'required|email',
            'message'         => 'required|string',
            'originalEmailId' => 'required|integer',
        ]);

        $to              = $request->input('to');
        $message         = $request->input('message');
        $originalEmailId = $request->input('originalEmailId');

        
        Mail::raw($message, function ($mail) use ($to) {
            $mail->to($to)
                ->subject("Re: Your previous email")
                ->from(config('mail.from.address'), config('mail.from.name'));
        });

        return response()->json(['message' => 'Reply sent successfully.'], 200);
    }

    public function markAsTrash($id)
    {
        $email = Email::findOrFail($id);
        $email->update(['is_trash' => true]);

        

        return response()->json(['message' => 'Email moved to trash']);
    }

    public function sentMarkAsTrash($id)
    {
        $email = SentEmail::findOrFail($id);
        $email->update(['is_trash' => 1]);

        

        return response()->json(['message' => 'Email moved to trash']);
    }

    public function markAsArchive($id)
    {
        $email = Email::findOrFail($id);
        $email->update(['is_archive' => true]);

        

        return response()->json(['message' => 'Email moved to Archive']);
    }

    public function restoreEmail($id)
    {
        $email = Email::findOrFail($id);
        $email->update(['is_trash' => false]);

        

        return response()->json(['message' => 'Email moved to trash']);
    }
    
    public function markAsRead($id)
    {
        $email = Email::findOrFail($id);

        
        $email->update([
            'is_read' => 1,
        ]);

        return response()->json(['message' => 'Email marked as read successfully']);
    }

    public function toggleStar($id)
    {
        $email = Email::findOrFail($id);

        $email->update([
            'is_starred' => 1,
        ]);

        return response()->json(['message' => 'Star status toggled successfully']);
    }

    public function deleteEmail($id)
    {
        $email = Email::findOrFail($id);

        
        $config       = EmailConfig::find($email->email_config_id); 
        $userAccounts = json_decode($config->username);

        if (is_array($userAccounts)) {
            foreach ($userAccounts as $account) {

                if (isset($account->email) && isset($account->password)) {
                    try {
                        $clientManager = new ClientManager();

                        $client = $clientManager->make([
                            'host'       => $config->host,
                            'port'       => $config->port,
                            'encryption' => $config->encryption,
                            'username'   => $account->email,
                            'password'   => $account->password,
                            'protocol'   => $config->driver,
                        ]);

                        $client->connect();

                        
                        $folder  = $client->getFolder('INBOX'); 
                        $message = $folder->messages()->getMessageById($email->message_id);

                        
                        if ($message) {
                            $message->delete();
                            $client->expunge(); 
                        }

                        
                        $email->delete();

                        return response()->json(['message' => 'Email deleted successfully']);
                    } catch (\Exception $e) {

                    }
                }
            }
        }

    }

    

    public function store(Request $request)
    {
        
        

        
        if (!$request->filled('message') && !$request->filled('subject') && !$request->filled('to') && !$request->hasFile('attachments')) {
            
            return response()->json(['message' => 'No draft data to save'], 204); 
        }

        
        $validated = $request->validate([
            'from'        => 'required|email',
            'to'          => 'nullable|email',
            'cc'          => 'nullable|email',
            'subject'     => 'nullable|string',
            'message'     => 'nullable|string',
            'attachments' => 'nullable|array',
        ]);

        try {
            
            $draft          = new Draft(); 
            $draft->from    = $validated['from'];
            $draft->to      = $validated['to'];
            $draft->cc      = $validated['cc'];
            $draft->subject = $validated['subject'];
            $draft->message = $validated['message'];

            
            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $filePath      = $file->store('attachments', 'public');
                    $attachments[] = $filePath;
                }
                $draft->attachments = json_encode($attachments); 
            } else {
                
            }

            
            $draft->user_id = Auth::id();
            $draft->save();

            

            
            return response()->json(['draft' => $draft], 201);

        } catch (\Exception $e) {
            
            
            return response()->json(['error' => 'Failed to save draft.'], 500);
        }
    }

    public function indexDraft()
    {
        
        $drafts = Draft::where('user_id', Auth::id())->get();
        return response()->json(['email' => $drafts]);
    }

    public function destroy($id)
    {
        $draft = Draft::where('user_id', Auth::id())->findOrFail($id);
        $draft->delete();
        return response()->json(['message' => 'Draft deleted successfully']);
    }

    public function getDraftAttachments($id)
    {
        $draft = Draft::findOrFail($id);

        
        $attachments = $draft->attachments ? json_decode($draft->attachments, true) : [];

        
        $attachmentData = array_map(function ($attachment) {
            return [
                'name' => basename($attachment), 
                'url' => asset('storage/' . $attachment), 
            ];
        }, $attachments);

        return response()->json(['attachments' => $attachmentData]);
    }

}