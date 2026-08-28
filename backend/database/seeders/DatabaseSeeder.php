<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminSeeder::class);
        $this->call(SettingsSeeder::class);
        $this->call(ContentBlockSeeder::class);

        // Guarded, not a blind factory create - every other seeder here is
        // safe to re-run (updateOrCreate/firstOrCreate, per each one's own
        // doc comment); this demo customer needs the same guarantee or a
        // plain `db:seed` re-run (no migrate:fresh first) fatals on the
        // email's unique constraint. Plain existence check rather than
        // firstOrCreate($attributes, $values): $values would win the
        // array_merge on create, so raw()'s own fake email in $values
        // would silently replace the real customer@example.com.
        if (! User::query()->where('email', 'customer@example.com')->exists()) {
            User::factory()->create([
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'email' => 'customer@example.com',
            ]);
        }

        $this->call(CategorySeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(PromotionSeeder::class);
        $this->call(LegalDocumentSeeder::class);
        $this->call(FunnelSeeder::class);
        $this->call(ReviewSeeder::class);
    }
}
