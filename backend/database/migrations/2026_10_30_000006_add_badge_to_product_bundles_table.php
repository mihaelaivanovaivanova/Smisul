<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The same "НАЙ-ПОПУЛЯРЕН"/"НАЙ-ИЗГОДЕН" style badge FunnelConfig's
     * packages already carry (see FunnelSeeder) - admin-editable per kit,
     * optional.
     */
    public function up(): void
    {
        Schema::table('product_bundles', function (Blueprint $table) {
            $table->string('badge')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('product_bundles', function (Blueprint $table) {
            $table->dropColumn('badge');
        });
    }
};
