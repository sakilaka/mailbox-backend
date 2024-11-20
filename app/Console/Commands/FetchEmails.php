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

    // public function handle()
    // {
    //     Log::info('Starting email fetching process...');

    //     try {
    //         $client = Client::account('default'); // Make sure 'default' matches your configuration
    //         Log::info('Attempting to connect to IMAP server...');

    //         $client->connect(); // Attempt to connect to the IMAP server
    //         Log::info('Connected to IMAP server successfully.');

    //         $folders = $client->getFolders();
    //         Log::info('Fetched folders:', ['folders' => $folders->pluck('name')]);

    //         foreach ($folders as $folder) {
    //             Log::info('Processing folder:', ['folder' => $folder->name]);
    //             $messages = $folder->messages()->all()->get();
    //             Log::info('Fetched messages count:', ['count' => $messages->count()]);

    //             foreach ($messages as $message) {
    //                 Log::info('Processing message:', [
    //                     'from' => $message->getFrom()[0]->mail,
    //                     'subject' => $message->getSubject()
    //                 ]);

    //                 // Check if the message is read by checking its flags
    //                 $isRead = $message->getFlags()->has('\\Seen');

    //                 Email::create([
    //                     'email_config_id' => 1, // Adjust with the correct config ID if dynamic
    //                     'sender' => $message->getFrom()[0]->mail,
    //                     'subject' => $message->getSubject(),
    //                     'body' => $message->getTextBody(),
    //                     'snippet' => substr($message->getTextBody(), 0, 50),
    //                     'is_read' => $isRead,
    //                 ]);
    //             }
    //         }
    //     } catch (\Exception $e) {
    //         Log::error('Error fetching emails:', ['error' => $e->getMessage()]);
    //     }
    // }

    
    public function handle()
    {
        Log::info('Starting email fetching process...');
        
        $emailConfigs = EmailConfig::all();
    
        foreach ($emailConfigs as $config) {
            $userAccounts = json_decode($config->username);
    
            if (is_array($userAccounts)) {
                foreach ($userAccounts as $account) {
                    if (isset($account->email) && isset($account->password)) {
                        try {
                            $clientManager = new ClientManager();
    
                            $client = $clientManager->make([
                                'host'          => $config->host,
                                'port'          => $config->port,
                                'encryption'    => $config->encryption,
                                'username'      => $account->email,
                                'password'      => $account->password,
                                'protocol'      => $config->driver,
                            ]);
    
                            $client->connect();
                            $folders = $client->getFolders();
    
                            foreach ($folders as $folder) {
                                $messages = $folder->messages()->all()->get();
    
                                foreach ($messages as $message) {
                                    $messageId = $message->getMessageId() ?: null;
    
                                    $existingEmail = Email::where('email_config_id', $config->id)
                                                           ->where('message_id', $messageId)
                                                           ->first();
    
                                    if (!$existingEmail) {
                                        Log::info('No existing email found for message_id:', ['message_id' => $messageId]);
    
                                        $from = $message->getFrom();
                                        $senderEmail = (!empty($from) && isset($from[0]->mail)) ? $from[0]->mail : 'unknown';
    
                                        // Handle attachments
                                        $attachments = [];
                                        if ($message->hasAttachments()) {
                                            foreach ($message->getAttachments() as $attachment) {
                                                // Generate a unique file name to avoid conflicts
                                                $uniqueName = uniqid() . '_' . $attachment->getName();
                                                
                                                // Define the relative and absolute paths
                                                $relativePath = 'attachments/email/' . $uniqueName;
                                                $attachmentPath = storage_path('app/public/' . $relativePath);
                                                
                                                // Save the attachment content to the file
                                                file_put_contents($attachmentPath, $attachment->getContent());
                                                
                                                // Save only the relative path to the array
                                                $attachments[] = $relativePath;
                                            }
                                        }
    
                                        Email::create([
                                            'email_config_id' => $config->id,
                                            'sender' => $senderEmail,
                                            'subject' => $message->getSubject(),
                                            'body' => $message->getTextBody(),
                                            'snippet' => substr($message->getTextBody(), 0, 50),
                                            'is_read' => $message->getFlags()->has('\\Seen'),
                                            'message_id' => $messageId,
                                            'attachment' => json_encode($attachments), // Store attachment paths as JSON
                                        ]);
                                    } else {
                                        Log::info('Existing email found:', ['id' => $existingEmail->id, 'message_id' => $existingEmail->message_id]);
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            Log::error("Error fetching emails for {$account->email}: {$e->getMessage()}");
                        }
                    }
                }
            } else {
                Log::error("Invalid username format for config ID {$config->id}");
            }
        }
    }
    
    

}