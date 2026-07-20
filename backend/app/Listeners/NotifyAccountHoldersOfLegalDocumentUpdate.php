<?php

namespace App\Listeners;

use App\Enums\Role;
use App\Events\Legal\LegalDocumentUpdated;
use App\Models\User;
use App\Notifications\LegalDocumentUpdatedNotification;
use Illuminate\Support\Facades\Notification;

class NotifyAccountHoldersOfLegalDocumentUpdate
{
    public function handle(LegalDocumentUpdated $event): void
    {
        // Every registered customer's account relationship runs on Terms/
        // Privacy (see LegalDocumentType::requiredForAccount) regardless of
        // whether they've placed an order, so all of them are notified —
        // not just those with a prior Consent row for this type.
        $customers = User::query()->where('role', Role::Customer)->get();

        if ($customers->isEmpty()) {
            return;
        }

        Notification::send($customers, new LegalDocumentUpdatedNotification($event->document));
    }
}
