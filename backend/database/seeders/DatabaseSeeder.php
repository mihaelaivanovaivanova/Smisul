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

        // Seeders also run on staging hosts where Composer is installed with
        // --no-dev, so Faker-backed model factories are not available.
        $testCustomer = User::query()->firstOrNew(['email' => 'customer@example.com']);
        $testCustomer->forceFill([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'password' => 'password',
            'email_verified_at' => now(),
            'gdpr_consent_at' => now(),
        ])->save();

        $this->call(CategorySeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(PromotionSeeder::class);
        $this->call(LegalDocumentSeeder::class);
        $this->call(FunnelSeeder::class);
        $this->call(ReviewSeeder::class);
    }
}
