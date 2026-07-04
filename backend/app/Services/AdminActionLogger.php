<?php

namespace App\Services;

use App\Models\AdminActionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Records admin-initiated writes for the admin Logs screen. Order status
 * changes are already captured by OrderStatusHistory (which records
 * changed_by_user_id) and aren't duplicated here — this covers the writes
 * that otherwise have no audit trail at all: catalog CRUD, settings, and
 * legal document publishing.
 */
class AdminActionLogger
{
    /**
     * @param  array<string, mixed>  $changes
     */
    public function log(User $user, string $action, ?Model $subject = null, array $changes = []): void
    {
        AdminActionLog::create([
            'user_id' => $user->getKey(),
            'action' => $action,
            'subject_type' => $subject !== null ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'changes' => $changes === [] ? null : $changes,
        ]);
    }
}
