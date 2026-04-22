<?php
// app/Mail/ResetPasswordMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $email;
    public $resetUrl;

    public function __construct($token, $email)
    {
        $this->token = $token;
        $this->email = $email;
        $this->resetUrl = env('FRONTEND_URL', 'http://localhost:3000') . '/reset-password?token=' . $token . '&email=' . $email;
    }

    public function build()
    {
        return $this->subject('Reset Your Password - U-Connect')
                    ->view('emails.reset-password')
                    ->with([
                        'resetUrl' => $this->resetUrl,
                        'token' => $this->token,
                        'email' => $this->email
                    ]);
    }
}