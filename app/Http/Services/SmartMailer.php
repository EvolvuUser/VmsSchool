<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SmartMailer
{
    public function send($to, $subject, $view, $data = [])
    {
        $smtp = $this->getAvailableSmtp();
        //  dd($smtp);
        if (!$smtp) {
            throw new \Exception('No SMTP account available.');
        }

        Config::set('mail.mailers.smtp', [
            'transport'  => 'smtp',
            'host'       => $smtp->mail_host,
            'port'       => $smtp->mail_port,
            'encryption' => $smtp->mail_encryption,
            'username'   => $smtp->mail_username,
            'password'   => $smtp->mail_password,
        ]);

        Mail::mailer('smtp')->send($view, $data, function ($message) use ($to, $subject, $smtp) {
            $message->to($to)
                ->subject($subject)
                ->from($smtp->mail_from_address, $smtp->mail_from_name)
                ->replyTo($smtp->mail_from_address, $smtp->mail_from_name);
        });

        $this->incrementSentCount($smtp);
    }

    private function getAvailableSmtp()
    {
        $today = now()->toDateString();

        $smtpAccounts = DB::table('emails_smtp_details')
            ->where('active', 'Y')
            ->orderBy('priority', 'asc')
            ->get();

        foreach ($smtpAccounts as $smtp) {
            // dd($today);
            if ($smtp->last_sent_date !== $today) {
                DB::table('emails_smtp_details')
                    ->where('email_smtp_id', $smtp->email_smtp_id)
                    ->update([
                        'emails_sent_today' => 0,
                        'last_sent_date' => $today,
                    ]);
                $smtp->emails_sent_today = 0;
            }

            if ($smtp->emails_sent_today < $smtp->daily_limit) {
                return $smtp;
            }
        }

        return null;
    }

    private function incrementSentCount($smtp)
    {
        DB::table('emails_smtp_details')
            ->where('email_smtp_id', $smtp->email_smtp_id)
            ->increment('emails_sent_today');

        DB::table('emails_smtp_details')
            ->where('email_smtp_id', $smtp->email_smtp_id)
            ->update(['last_sent_date' => now()->toDateString()]);
    }
}
