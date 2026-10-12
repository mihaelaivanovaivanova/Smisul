<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The one list every outbound marketing send (BroadcastService) and
        // the one-click unsubscribe link both consult before anything goes
        // out - independent of whether the address belongs to a User (whose
        // own marketing_consent/newsletter_subscription columns are a
        // logged-in preference, not reachable from an anonymous unsubscribe
        // click) or a FunnelLead (which has no consent column at all). Any
        // email in this table is permanently excluded from broadcast sends
        // regardless of what Consent/User rows say, until a row is deleted.
        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            // unsubscribed (one-click link), hard_bounced, complained -
            // whatever Brevo's webhook or the unsubscribe link reports.
            $table->string('reason');
            $table->string('source')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
