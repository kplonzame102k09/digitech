<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminAccountCreated extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;

    public string $userId;

    public ?string $username;

    public string $password;

    public bool $mustChangePassword;

    public string $loginUrl;

    public function __construct(
        string $name,
        string $userId,
        ?string $username,
        string $password,
        bool $mustChangePassword = false,
    ) {
        $this->name = $name;
        $this->userId = $userId;
        $this->username = $username;
        $this->password = $password;
        $this->mustChangePassword = $mustChangePassword;
        $this->loginUrl = url('/auth/login');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Digitech College account has been created',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-created',
        );
    }
}