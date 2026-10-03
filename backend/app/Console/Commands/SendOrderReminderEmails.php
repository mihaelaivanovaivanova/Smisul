<?php

namespace App\Console\Commands;

use App\Services\OrderReminderService;
use Illuminate\Console\Command;

/**
 * Scheduled daily (see routes/console.php) - thin wrapper around
 * OrderReminderService, which owns the actual eligibility/sending logic
 * so it stays unit-testable without the console layer.
 */
class SendOrderReminderEmails extends Command
{
    protected $signature = 'orders:send-thirty-day-reminders';

    protected $description = 'Email every order that was marked Delivered 30+ days ago and hasn\'t had its reminder sent yet';

    public function handle(OrderReminderService $service): int
    {
        $result = $service->sendDueReminders();

        $this->info("Sent {$result['sent']} reminder email(s).");

        if ($result['failed'] > 0) {
            $this->warn("{$result['failed']} failed to send - see the log for details.");
        }

        return self::SUCCESS;
    }
}
