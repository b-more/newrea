<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $account_number_to_send;
    public $account_owner_to_send;
    public $api_secret_id_to_send;
    public $api_access_token_to_send;
    public $password_to_send;
    public $merchant_code_to_send;

    /**
     * Create a new message instance.
     */
    public function __construct($password_to_send,$merchant_code_to_send)
    {
        $this->password_to_send = $password_to_send;
        $this->merchant_code_to_send = $merchant_code_to_send;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $account_number = $this->account_number_to_send;
        return new Envelope(
            subject: 'Welcome to your REA Agent with merchant code '.$merchant_code_to_send,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
            with: [
                'merchant_code_to_send' => $this->merchant_code_to_send,
                'password_to_send' => $this->password_to_send
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
