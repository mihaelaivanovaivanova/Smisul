<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the three escalating pickup_reminder_*_sent_at columns (never
 * populated - the automatic reminder sequence they backed was gated behind
 * SHIPMENT_PICKUP_REMINDERS_ENABLED, always false, and was scrapped before
 * ever being turned on - see ShipmentPickupNotificationService's own
 * docblock for what replaced it: a single manual "Send pickup notification"
 * button, not a scheduled sequence) with one column tracking the last
 * manual send. ready_for_pickup_at is untouched - still set automatically
 * by ShipmentTrackingSyncService and still shown in the admin UI, just no
 * longer anything an email schedule counts from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn(['pickup_reminder_sent_at', 'pickup_reminder_2_sent_at', 'pickup_reminder_3_sent_at']);
            $table->timestamp('pickup_notification_sent_at')->nullable()->after('ready_for_pickup_at');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('pickup_notification_sent_at');
            $table->timestamp('pickup_reminder_sent_at')->nullable()->after('ready_for_pickup_at');
            $table->timestamp('pickup_reminder_2_sent_at')->nullable()->after('pickup_reminder_sent_at');
            $table->timestamp('pickup_reminder_3_sent_at')->nullable()->after('pickup_reminder_2_sent_at');
        });
    }
};
