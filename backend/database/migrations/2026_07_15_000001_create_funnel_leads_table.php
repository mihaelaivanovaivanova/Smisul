<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Emails captured by the funnel landing page's opt-in block —
        // visitors who aren't ready to buy but want to hear about
        // promotions. Unique on email: re-submitting is a no-op, not a
        // duplicate row (see FunnelLeadController).
        Schema::create('funnel_leads', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_leads');
    }
};
