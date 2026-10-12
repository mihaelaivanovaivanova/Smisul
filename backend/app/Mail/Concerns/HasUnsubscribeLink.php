<?php

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * Shared by every Mailable that is promotional rather than a transactional
 * notice the recipient needs regardless of preference (order confirmations,
 * invoices, etc. don't use this). Required for Gmail/Yahoo's bulk-sender
 * rules (RFC 8058 one-click unsubscribe) and good practice generally -
 * without List-Unsubscribe-Post, Gmail shows its own "unsubscribe" link
 * next to the sender name using whatever it can infer, which is a worse
 * experience and a worse spam signal than offering a real one.
 *
 * Permanent (not temporarySignedRoute) - an unsubscribe link in an email
 * sitting unread for months must still work the day it's finally opened.
 */
trait HasUnsubscribeLink
{
    protected function unsubscribeUrl(string $email): string
    {
        return URL::signedRoute('unsubscribe', ['email' => $email]);
    }

    protected function unsubscribeHeaders(string $email): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl($email).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }
}
