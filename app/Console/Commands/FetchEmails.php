<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Models\EmailConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\ClientManager;

class FetchEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch emails from the IMAP server';

   

    public function handle()
    {
        Log::info('Starting email fetching process...');
    
        $emailConfigs = EmailConfig::all();
        foreach ($emailConfigs as $config) {
            Log::info("Processing email configuration", ['config_id' => $config->id]);
    
            $userAccounts = json_decode($config->username);
    
            if (is_array($userAccounts)) {
                foreach ($userAccounts as $account) {
                    if (isset($account->email) && isset($account->password)) {
                        Log::info("Processing account", ['email' => $account->email]);
    
                        try {
                            $clientManager = new ClientManager();
                            $client = $clientManager->make([
                                'host'       => $config->host,
                                'port'       => $config->incoming_port,
                                'encryption' => $config->encryption,
                                'username'   => $account->email,
                                'password'   => $account->password,
                                'protocol'   => $config->driver,
                            ]);
    
                            $client->connect();
                            Log::info("Connected successfully", ['email' => $account->email]);
    
                            $folders = $client->getFolders();
    
                            foreach ($folders as $folder) {
                                $messages = $folder->messages()->all()->get();
    
                                foreach ($messages as $message) {
                                    $messageId = $message->getMessageId() ?: null;
    
                                    $existingEmail = Email::where('email_config_id', $config->id)
                                        ->where('message_id', $messageId)
                                        ->first();
    
                                    if (!$existingEmail) {
                                        $from = $message->getFrom();
                                        $senderEmail = (!empty($from) && isset($from[0]->mail)) ? $from[0]->mail : 'unknown';
    
                                        // Retrieve HTML content
                                        $htmlBody = $message->getHtmlBody();
                                        $textBody = $message->getTextBody();
                                        $body = $htmlBody ?: $textBody;
    
                                        // Handle attachments
                                        $attachments = [];
                                        if ($message->hasAttachments()) {
                                            foreach ($message->getAttachments() as $attachment) {
                                                $uniqueName = uniqid() . '_' . $attachment->getName();
                                                $relativePath = 'attachments/' . $uniqueName;
                                                $attachmentPath = public_path($relativePath);
    
                                                try {
                                                    file_put_contents($attachmentPath, $attachment->getContent());
                                                    $attachments[] = $relativePath;
                                                } catch (\Exception $e) {
                                                    Log::error("Error saving attachment", [
                                                        'path' => $attachmentPath,
                                                        'error' => $e->getMessage(),
                                                    ]);
                                                }
                                            }
                                        }
    
                                        Email::create([
                                            'email_config_id' => $config->id,
                                            'sender'          => $senderEmail,
                                            'subject'         => $message->getSubject(),
                                            'body'            => $body,
                                            'content_type'    => $htmlBody ? 'html' : 'text',
                                            'snippet'         => substr(strip_tags($body), 0, 50),
                                            'is_read'         => $message->getFlags()->has('\\Seen'),
                                            'message_id'      => $messageId,
                                            'attachment'      => json_encode($attachments),
                                        ]);
    
                                        Log::info("Email saved successfully", ['message_id' => $messageId]);
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error("Error processing account", [
                                'email' => $account->email,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        }
    }
    
}