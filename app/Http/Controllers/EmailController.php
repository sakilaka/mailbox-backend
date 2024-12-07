<?php

namespace App\Http\Controllers;

use App\Models\Draft;
use App\Models\Email;
use App\Models\EmailConfig;
use App\Models\Refferal;
use App\Models\ScheduleEmail;
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
        // Log the initial request data
        Log::info('sendEmail request received', ['request_data' => $request->all()]);

        try {
            $validated = $request->validate([
                'from'          => 'required|email',
                'to'            => 'required|email',
                // 'cc'            => 'nullable|email',
                'subject'       => 'required|string',
                'message'       => 'required|string',
                'attachments'   => 'nullable|array',
                'attachments.*' => 'nullable|file',
            ]);

            // Log successful validation
            Log::info('Validation successful', ['validated_data' => $validated]);
        } catch (\Exception $e) {
            // Log validation failure
            Log::error('Validation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Validation failed.'], 422);
        }

        $emailData = [
            'from'    => $validated['from'],
            'to'      => $validated['to'],
            'cc'      => $validated['cc'] ?? '',
            'subject' => $validated['subject'],
            'message' => $validated['message'],
        ];

        // Log email data
        Log::info('Email data prepared', ['email_data' => $emailData]);

        // Find the email configuration dynamically
        $emailConfig = EmailConfig::whereJsonContains('username', ['email' => $validated['from']])->first();
        if (!$emailConfig) {
            // Log missing email configuration
            Log::error('Email configuration not found', ['email' => $validated['from']]);
            return response()->json(['error' => 'Email configuration not found for the given email address.'], 404);
        }

        $userAccounts    = json_decode($emailConfig->username, true);
        $selectedAccount = collect($userAccounts)->firstWhere('email', $validated['from']);

        if (!$selectedAccount) {
            // Log missing account in email configuration
            Log::error('No matching email account found', ['email' => $validated['from']]);
            return response()->json(['error' => 'No matching email account found for sending email.'], 404);
        }

        try {
            // Configure mail dynamically using the host from the database
            config([
                'mail.mailers.smtp.username'   => $selectedAccount['email'],
                'mail.mailers.smtp.password'   => $selectedAccount['password'],
                'mail.mailers.smtp.host'       => $emailConfig->smtp_host,
                'mail.mailers.smtp.port'       => 465,
                'mail.mailers.smtp.encryption' => "tls",
            ]);

            // Log successful mail configuration
            Log::info('Mail configuration set', ['smtp_config' => config('mail.mailers.smtp')]);
        } catch (\Exception $e) {
            // Log mail configuration failure
            Log::error('Mail configuration failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to configure mail settings.'], 500);
        }

        try {
            Mail::send([], [], function ($message) use ($emailData, $request) {
                $message->from($emailData['from'])
                    ->to($emailData['to'])
                    ->subject($emailData['subject'])
                    ->html($emailData['message']);

                // Only add CC if it's not empty
                if (!empty($emailData['cc'])) {
                    $message->cc($emailData['cc']);
                }

                // Attach files if available
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        $message->attach($file->getRealPath(), [
                            'as'   => $file->getClientOriginalName(),
                            'mime' => $file->getMimeType(),
                        ]);
                    }

                    // Log file attachment
                    Log::info('Attachments added to email', ['attachments' => $request->file('attachments')]);
                }
            });

            // Log email sent
            Log::info('Email sent successfully', ['email_data' => $emailData]);
        } catch (\Exception $e) {
            // Log email sending failure
            Log::error('Email sending failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to send email.'], 500);
        }

        try {
            $sentEmail = new SentEmail();

            $sentEmail->user_id = Auth::id();
            $sentEmail->from    = $emailData['from'];
            $sentEmail->to      = $emailData['to'];
            $sentEmail->cc      = $emailData['cc'] ?? '';
            $sentEmail->subject = $emailData['subject'];
            $sentEmail->message = $emailData['message'];

            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    // Define the public path for saving attachments
                    $destinationPath = public_path('attachments');
                    $fileName        = time() . '_' . $file->getClientOriginalName();

                    // Move the file to public/attachments
                    $file->move($destinationPath, $fileName);

                    // Save the relative path to the attachments array
                    $attachments[] = "attachments/$fileName";

                    // Optionally log each file save
                    Log::info('Attachment saved', ['path' => $destinationPath, 'name' => $fileName]);
                }

                // Save attachments to the database
                $sentEmail->attachment = json_encode($attachments);

                // Log saved attachments
                Log::info('Attachments saved to database', ['attachments' => $attachments]);
            }

            $sentEmail->save();

            // Log email saved to database
            Log::info('Email saved to database', ['email' => $sentEmail]);
        } catch (\Exception $e) {
            // Log database save failure
            Log::error('Failed to save email to database', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to save email to database.'], 500);
        }

        return response()->json(['message' => 'Email sent and stored successfully']);
    }

    public function scheduleEmail(Request $request)
    {
        // Log the initial request data
        Log::info('scheduleEmail request received', ['request_data' => $request->all()]);

        try {
            $validated = $request->validate([
                'from'          => 'required|email',
                'to'            => 'required|email',
                'subject'       => 'required|string',
                'message'       => 'required|string',
                'attachments'   => 'nullable|array',
                'attachments.*' => 'nullable|file',
                'schedule_time' => 'required|date', // Validate the schedule time
            ]);

            // Log successful validation
            Log::info('Validation successful', ['validated_data' => $validated]);
        } catch (\Exception $e) {
            // Log validation failure
            Log::error('Validation failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Validation failed.'], 422);
        }

        // Prepare email data
        $emailData = [
            'from'          => $validated['from'],
            'to'            => $validated['to'],
            'cc'            => $validated['cc'] ?? '',
            'subject'       => $validated['subject'],
            'message'       => $validated['message'],
            'schedule_time' => $validated['schedule_time'], // Schedule time
        ];

        // Save scheduled email to database
        try {
            $scheduledEmail                 = new ScheduleEmail();
            $scheduledEmail->user_id        = Auth::id();
            $scheduledEmail->from           = $emailData['from'];
            $scheduledEmail->to             = $emailData['to'];
            $scheduledEmail->cc             = $emailData['cc'];
            $scheduledEmail->subject        = $emailData['subject'];
            $scheduledEmail->message        = $emailData['message'];
            $scheduledEmail->scheduled_time = $emailData['schedule_time'];

            // Handle attachments if any
            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $destinationPath = public_path('attachments');
                    $fileName        = time() . '_' . $file->getClientOriginalName();
                    $file->move($destinationPath, $fileName);
                    $attachments[] = "attachments/$fileName";
                }
                $scheduledEmail->attachments = json_encode($attachments);
            }

            $scheduledEmail->save();
            Log::info('Scheduled email saved to database', ['email' => $scheduledEmail]);
        } catch (\Exception $e) {
            Log::error('Failed to save scheduled email', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to save scheduled email.'], 500);
        }

        // Return success message
        return response()->json(['message' => 'Email scheduled successfully']);
    }

    public function getUserEmails()
    {
        try {
            // Fetch all EmailConfig records
            $emailConfigs   = EmailConfig::all();
            $emailAddresses = [];

            foreach ($emailConfigs as $emailConfig) {
                if ($emailConfig->username) {
                    // Decode the username field (assumed to be JSON)
                    $emails = json_decode($emailConfig->username, true);

                    if (is_array($emails)) {
                        // Extract email addresses and merge them into the result array
                        $emailAddresses = array_merge($emailAddresses, array_column($emails, 'email'));
                    } else {
                        Log::warning("Invalid JSON in EmailConfig ID: {$emailConfig->id}");
                    }
                }
            }

            return response()->json(['emails' => $emailAddresses]);
        } catch (\Exception $e) {
            Log::error("An error occurred while fetching user emails: {$e->getMessage()}");
            return response()->json(['error' => 'Failed to fetch emails'], 500);
        }
    }

    public function sentEmails()
    {
        $email = SentEmail::orderBy('created_at', 'desc')->where('is_trash', 0)->where('user_id', Auth::id())->get();
        $totalSentEmail = $email->count();
        
        return response()->json([
            'email' => $email,
            'totalSentEmail' => $totalSentEmail
        ]);
    }

    public function index()
    {

        $referralIds = Refferal::where('user_id', Auth::id())->pluck('referral_id');

        $userIds = $referralIds->push(Auth::id());

        $emailConfigs = EmailConfig::whereIn('user_id', $userIds)->pluck('id');

        $emails = Email::query()
            ->whereIn('email_config_id', $emailConfigs)
            ->where('is_trash', 0)
            ->where('is_archive', 0)
            ->orderBy('created_at', 'desc')
            ->get();

        $totalEmail = $emails->count();
        return response()->json([
            'email'      => $emails,
            'totalEmail' => $totalEmail,
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
        $email = Email::where('is_starred', 1)->where('is_trash', 0)->orderBy('created_at', 'desc')->get();
        return response()->json([
            'email' => $email,
        ]);
    }

    public function indexArchive()
    {
        $email = Email::where('is_archive', 1)->where('is_trash', 0)->orderBy('created_at', 'desc')->get();
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
        try {
            $email = Email::findOrFail($id);
            Log::info("Email found. ID: {$email->id}, Subject: {$email->subject}");

            $config = EmailConfig::find($email->email_config_id);
            if (!$config) {
                Log::error("Email configuration not found for Email ID: {$email->id}");
                return response()->json(['error' => 'Email configuration not found'], 404);
            }

            Log::info("Config found for email. Host: {$config->host}");

            // Ensure that $config->username is properly decoded as an array
            $userAccounts = json_decode($config->username);
            if (is_array($userAccounts)) {
                Log::info("Found " . count($userAccounts) . " user accounts for deletion.");

                foreach ($userAccounts as $account) {
                    // Ensure account has the necessary properties
                    if (isset($account->email) && isset($account->password)) {
                        try {
                            $clientManager = new ClientManager();
                            Log::info("Connecting to email server using credentials: " . $account->email);

                            $client = $clientManager->make([
                                'host'       => $config->host,
                                'port'       => $config->port,
                                'encryption' => $config->encryption,
                                'username'   => $account->email,
                                'password'   => $account->password,
                                'protocol'   => $config->driver,
                            ]);

                            // Connect to the email server
                            $client->connect();
                            Log::info("Successfully connected to the email server.");

                            // Fetch the folder and the message to delete
                            $folder   = $client->getFolder('INBOX');
                            $messages = $folder->messages()->all(); // Fetch all messages

                            // Find the message by ID
                            $message = null;
                            foreach ($messages as $msg) {
                                if ($msg->getId() == $email->message_id) {
                                    $message = $msg;
                                    break;
                                }
                            }

                            if ($message) {
                                Log::info("Message found. ID: {$message->getId()}, Subject: {$message->getSubject()}");
                                $message->delete();
                                $client->expunge();
                                Log::info("Message deleted and expunged.");
                            } else {
                                Log::warning("Message with ID {$email->message_id} not found on the server.");
                            }

                            // Delete the email from the database
                            $email->delete();
                            Log::info("Email record deleted from database.");

                            return response()->json(['message' => 'Email deleted successfully']);
                        } catch (\Exception $e) {
                            Log::error("Error deleting email for account: {$account->email}. Error: {$e->getMessage()}");
                            return response()->json(['error' => 'Failed to delete email'], 500);
                        }
                    } else {
                        Log::warning("Account missing email or password for deletion.");
                    }
                }
            } else {
                Log::error("User accounts are not in the correct format or empty.");
            }

            return response()->json(['error' => 'Failed to delete email'], 500);
        } catch (\Exception $e) {
            Log::error("An error occurred while attempting to delete email with ID {$id}. Error: {$e->getMessage()}");
            return response()->json(['error' => 'An error occurred while deleting the email'], 500);
        }
    }

    public function store(Request $request)
    {
        // Log the incoming request data
        Log::info('Draft save request received', ['request_data' => $request->all()]);

        if (
            !$request->filled('message') &&
            !$request->filled('subject') &&
            !$request->filled('to') &&
            !$request->hasFile('attachments')
        ) {
            Log::warning('No draft data provided to save');
            return response()->json(['message' => 'No draft data to save'], 204);
        }

        try {
            $validated = $request->validate([
                'from'        => 'required|email',
                'to'          => 'nullable|email',
                'cc'          => 'nullable|email',
                'subject'     => 'nullable|string',
                'message'     => 'nullable|string',
                'attachments' => 'nullable|array',
            ]);

            Log::info('Draft data validated successfully', ['validated_data' => $validated]);

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
                    Log::info('Attachment saved', ['file_path' => $filePath]);
                }
                $draft->attachments = json_encode($attachments);
            } else {
                Log::info('No attachments provided');
            }

            $draft->user_id = Auth::id();
            Log::info('User ID set for draft', ['user_id' => Auth::id()]);

            $draft->save();
            Log::info('Draft saved successfully', ['draft' => $draft]);

            return response()->json(['draft' => $draft], 201);

        } catch (\Exception $e) {
            Log::error('Failed to save draft', ['error' => $e->getMessage()]);
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
                'url'  => asset('storage/' . $attachment),
            ];
        }, $attachments);

        return response()->json(['attachments' => $attachmentData]);
    }

    public function downloadAttachment($path)
    {

        $decodedPath = urldecode($path);

        $filePath = public_path($decodedPath);

        if (!file_exists($filePath)) {

            abort(404, 'File not found.');
        }

        return response()->download($filePath, basename($decodedPath));
    }

    public function allConfig()
    {
        $configs = EmailConfig::where('user_id', Auth::id())->get();
        return response()->json([
            'config' => $configs,
        ]);
    }

    public function deleteAllTrashedEmails()
    {
        try {
            $deletedRows = Email::where('is_trash', 1)->delete();

            if ($deletedRows > 0) {
                return response()->json(['message' => 'All trashed emails deleted successfully.'], 200);
            }

            return response()->json(['message' => 'No trashed emails to delete.'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete emails.', 'details' => $e->getMessage()], 500);
        }
    }

    public function moveEmailsToTrash(Request $request)
    {
        $emailIds = $request->input('emailIds');

        if (!is_array($emailIds) || empty($emailIds)) {
            return response()->json(['error' => 'No emails selected.'], 400);
        }

        try {
            Email::whereIn('id', $emailIds)->update(['is_trash' => true]);

            return response()->json(['message' => 'Emails moved to trash successfully.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to move emails to trash.'], 500);
        }
    }

}