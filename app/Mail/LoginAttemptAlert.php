<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoginAttemptAlert extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;

    public string $identifier;

    public string $role;

    public string $ip;

    public int $attempts;

    public string $lockedUntil;

    public string $supportUrl;

    public function __construct(
        string $name,
        string $identifier,
        string $role,
        string $ip,
        int $attempts,
        string $lockedUntil,
    ) {
        $this->name = $name;
        $this->identifier = $identifier;
        $this->role = $role;
        $this->ip = $ip;
        $this->attempts = $attempts;
        $this->lockedUntil = $lockedUntil;
        $this->supportUrl = url('/');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Security alert: repeated sign-in attempts on your account',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.login-attempt-alert',
        );
    }
}