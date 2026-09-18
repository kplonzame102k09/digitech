<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountRequestDenied extends Mailable
{
    use Queueable, SerializesModels;

    public string $name;

    public string $reason;

    public function __construct(
        string $name,
        string $reason,
    ) {
        $this->name = $name;
        $this->reason = $reason;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Update regarding your Digitech College account request',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-request-denied',
        );
    }
}