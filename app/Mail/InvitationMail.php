<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invitation to the Sports Entry Portal – '.$this->invitation->association->name);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.invitation');
    }
}
