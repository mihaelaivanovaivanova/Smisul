<?php

namespace App\Events\Legal;

use App\Models\LegalDocument;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a new version of a LegalDocumentType::requiredForAccount()
 * document (Terms of Service, Privacy Policy) is published — the ongoing
 * account relationship runs on these two, so every existing customer's
 * prior acceptance is now stale (see ConsentService::outstandingForAccount)
 * and needs to be told. Not fired for the other document types
 * (Right of Withdrawal, Cookie/Shipping/Returns policy): those aren't
 * tracked per-account, so there's no "stale acceptance" to notify about.
 */
class LegalDocumentUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly LegalDocument $document,
    ) {}
}
