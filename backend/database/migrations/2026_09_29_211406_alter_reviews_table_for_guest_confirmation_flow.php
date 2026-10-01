<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supports the guest email-confirmation review flow: user_id is now
     * nullable (a guest reviewer has no account), and the reviewer's own
     * typed email/display name/anonymity choice live on the review itself
     * rather than always being derived from a User row. confirmed_at is
     * null until the emailed confirmation link is clicked.
     */
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();

            $table->string('email')->nullable()->after('user_id');
            $table->string('display_name')->nullable()->after('email');
            $table->boolean('is_anonymous')->default(false)->after('display_name');
            $table->timestamp('confirmed_at')->nullable()->after('is_anonymous');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['email', 'display_name', 'is_anonymous', 'confirmed_at']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
