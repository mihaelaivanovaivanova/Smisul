<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            // Set once, the first time tracking sync observes
            // ShipmentStatus::ReadyForPickup for this shipment (see
            // ShipmentTrackingSyncService) - the clock the three pickup
            // reminders below count up from, independent of whenever sync
            // actually happens to run next.
            $table->timestamp('ready_for_pickup_at')->nullable()->after('estimated_delivery_at');

            // Each set once, on success, and never re-sent - same
            // once-only pattern as orders.thirty_day_reminder_sent_at (see
            // OrderReminderService). _sent_at is the immediate notification
            // (day 0); _2_/_3_ are the +3/+6 day follow-ups while the
            // shipment is still sitting unpicked.
            $table->timestamp('pickup_reminder_sent_at')->nullable()->after('ready_for_pickup_at');
            $table->timestamp('pickup_reminder_2_sent_at')->nullable()->after('pickup_reminder_sent_at');
            $table->timestamp('pickup_reminder_3_sent_at')->nullable()->after('pickup_reminder_2_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['ready_for_pickup_at', 'pickup_reminder_sent_at', 'pickup_reminder_2_sent_at', 'pickup_reminder_3_sent_at']);
        });
    }
};
