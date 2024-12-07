<?php

namespace App\Console\Commands;

use App\Models\EmailConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\ScheduleEmail;

class SendScheduledEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send scheduled emails at their scheduled time';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
{
    Log::info('Scheduled emails command started.');

    $current_time = Carbon::now()->addHours(6)->toDateTimeString();
    Log::info('Adjusted timestamp', ['now' => $current_time]);

    $emails = ScheduleEmail::where('scheduled_time', '<=', $current_time)
        ->whereNull('sent_at')
        ->get();

    if ($emails->isEmpty()) {
        Log::info('No scheduled emails found.');
        return;
    }

    foreach ($emails as $email) {
        try {
            // Fetch email configuration based on the "from" address
            $emailConfig = EmailConfig::whereJsonContains('username', ['email' => $email->from])->first();
            if (!$emailConfig) {
                Log::error('Email configuration not found for the given sender', ['email' => $email->from]);
                continue;
            }

            $userAccounts = json_decode($emailConfig->username, true);
            $selectedAccount = collect($userAccounts)->firstWhere('email', $email->from);

            if (!$selectedAccount) {
                Log::error('No matching email account found for sending email', ['email' => $email->from]);
                continue;
            }

            // Dynamically configure mail settings
            config([
                'mail.mailers.smtp.username'   => $selectedAccount['email'],
                'mail.mailers.smtp.password'   => $selectedAccount['password'],
                'mail.mailers.smtp.host'       => $emailConfig->smtp_host,
                'mail.mailers.smtp.port'       => 465,
                'mail.mailers.smtp.encryption' =>'tls',
            ]);

            Log::info('Mail configuration set for email', [
                'email_id' => $email->id,
                'config'   => config('mail.mailers.smtp'),
            ]);

            // Send the email
            Mail::send([], [], function ($message) use ($email) {
                $message->from($email->from)
                    ->to($email->to)
                    ->subject($email->subject)
                    ->html($email->message);

                if (!empty($email->cc)) {
                    $message->cc($email->cc);
                    Log::info('Added CC to email', ['email_id' => $email->id, 'cc' => $email->cc]);
                }

                if (!empty($email->attachments)) {
                    foreach (json_decode($email->attachments, true) as $file) {
                        $message->attach(public_path($file));
                        Log::info('Attached file to email', ['email_id' => $email->id, 'file' => $file]);
                    }
                }
            });

            Log::info('Email sent successfully', ['email_id' => $email->id]);

            // Mark email as sent
            $email->sent_at = Carbon::now()->addHours(6);
            $email->save();
            Log::info('Email marked as sent', ['email_id' => $email->id]);
        } catch (\Exception $e) {
            Log::error('Failed to send email', [
                'email_id' => $email->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    Log::info('Scheduled emails command completed.');
}

}