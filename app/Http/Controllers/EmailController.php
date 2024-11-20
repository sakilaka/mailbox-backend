<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailConfig;
use App\Models\SentEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webklex\PHPIMAP\ClientManager;

class EmailController extends Controller
{

    public function sendEmail(Request $request)
    {
    
        // Validate the request
        try {
            $validated = $request->validate([
                'from'       => 'required|email',
                'to'         => 'required|email',
                'cc'         => 'nullable|email',
                'subject'    => 'required|string',
                'message'    => 'required|string',
                'attachments' => 'nullable|array', // Validate as array of files
                'attachments.*' => 'file', // Each attachment should be a file
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Validation failed.'], 422);
        }
    
        // Define email data
        $emailData = [
            'from'    => $validated['from'],
            'to'      => $validated['to'],
            'cc'      => $validated['cc'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
        ];
        
    
        // Fetch email configuration
        $emailConfig = EmailConfig::whereJsonContains('username', ['email' => $validated['from']])->first();
        if (!$emailConfig) {
            
            return response()->json(['error' => 'Email configuration not found for the given email address.'], 404);
        }
        
    
        // Get user accounts associated with the email configuration
        $userAccounts = json_decode($emailConfig->username, true);
        $selectedAccount = collect($userAccounts)->firstWhere('email', $validated['from']);
    
        if (!$selectedAccount) {
            
            return response()->json(['error' => 'No matching email account found for sending email.'], 404);
        }
        
    
        // Configure the mail settings dynamically
        try {
            config([
                'mail.mailers.smtp.username' => $selectedAccount['email'],
                'mail.mailers.smtp.password' => $selectedAccount['password'],
                'mail.mailers.smtp.host'     => "smtp.hostinger.com",
                'mail.mailers.smtp.port'     => 465,
                'mail.mailers.smtp.encryption' => "tls",
            ]);
            
        } catch (\Exception $e) {
            
            return response()->json(['error' => 'Failed to configure mail settings.'], 500);
        }
    
        // Send the email
        try {
            Mail::send([], [], function ($message) use ($emailData, $request) {
                $message->from($emailData['from'])
                    ->to($emailData['to'])
                    ->cc($emailData['cc'])
                    ->subject($emailData['subject'])
                    ->html($emailData['message']);
    
                // Attach multiple files if available
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
    
        // Save the email to the database
        try {
            $sentEmail = new SentEmail();
            $sentEmail->from = $emailData['from'];
            $sentEmail->to = $emailData['to'];
            $sentEmail->cc = $emailData['cc'];
            $sentEmail->subject = $emailData['subject'];
            $sentEmail->message = $emailData['message'];
    
            // Save multiple attachment paths if there are any
            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $filePath = $file->store('attachments', 'public');
                    $attachments[] = $filePath;
                }
                $sentEmail->attachment = json_encode($attachments); // Store as JSON
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
        // Artisan::call('email:fetch');
    
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

        // Use a mail-sending service or library here
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

        // $client->moveMessage($email->message_id, 'Trash');

        return response()->json(['message' => 'Email moved to trash']);
    }
    
    public function sentMarkAsTrash($id)
    {
        $email = SentEmail::findOrFail($id);
        $email->update(['is_trash' => 1]);

        // $client->moveMessage($email->message_id, 'Trash');

        return response()->json(['message' => 'Email moved to trash']);
    }
    
    public function markAsArchive($id)
    {
        $email = Email::findOrFail($id);
        $email->update(['is_archive' => true]);

        // $client->moveMessage($email->message_id, 'Trash');

        return response()->json(['message' => 'Email moved to Archive']);
    }

    public function restoreEmail($id)
    {
        $email = Email::findOrFail($id);
        $email->update(['is_trash' => false]);

        // $client->moveMessage($email->message_id, 'Trash');

        return response()->json(['message' => 'Email moved to trash']);
    }
    // Mark email as read
    public function markAsRead($id)
    {
        $email = Email::findOrFail($id);

        // Update the email to be marked as read
        $email->update([
            'is_read' => 1
        ]);

        return response()->json(['message' => 'Email marked as read successfully']);
    }

    public function toggleStar($id)
    {
        $email = Email::findOrFail($id);

        $email->update([
            'is_starred' => 1
        ]);

        return response()->json(['message' => 'Star status toggled successfully']);
    }

    public function deleteEmail($id)
    {
        $email = Email::findOrFail($id);

        // Initialize IMAP client based on the email's configuration
        $config       = EmailConfig::find($email->email_config_id); // Assuming you store config ID with each email
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

                        // Find the message by message_id on IMAP server
                        $folder  = $client->getFolder('INBOX'); // Assuming the email is in INBOX
                        $message = $folder->messages()->getMessageById($email->message_id);

                        // Delete the message from IMAP if it exists
                        if ($message) {
                            $message->delete();
                            $client->expunge(); // Ensure it’s permanently deleted
                        }

                        // Finally, delete the email from the database
                        $email->delete();

                        return response()->json(['message' => 'Email deleted successfully']);
                    } catch (\Exception $e) {
                        
                    }
                }
            }
        }

    }

}