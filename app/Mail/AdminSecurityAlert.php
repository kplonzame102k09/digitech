<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminSecurityAlert extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;

    public string $identifier;

    public string $role;

    public string $ip;

    public int $attempts;

    public string $lockedUntil;

    public string $manageUrl;

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
        $this->manageUrl = url('/admin/dashboard');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Brute-force sign-in attempt blocked',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-security-alert',
        );
    }
}