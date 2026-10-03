<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Null until the daily reminder job (SendOrderReminderEmails /
     * OrderReminderService) sends this order's 30-days-since-delivery
     * reminder email - set only on a successful send, so a mail transport
     * failure leaves it eligible for retry the next day instead of
     * silently skipping the order forever.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('thirty_day_reminder_sent_at')->nullable()->after('delivery_notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('thirty_day_reminder_sent_at');
        });
    }
};
