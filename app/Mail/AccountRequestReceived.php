<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;

    public string $role;

    public string $email;

    public string $requestId;

    public string $purpose;

    public string $manageUrl;

    public function __construct(
        string $name,
        string $role,
        string $email,
        string $requestId,
        string $purpose,
    ) {
        $this->name = $name;
        $this->role = $role;
        $this->email = $email;
        $this->requestId = $requestId;
        $this->purpose = $purpose;
        $this->manageUrl = url('/admin/account-requests');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New account request received',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-request-received',
        );
    }
}