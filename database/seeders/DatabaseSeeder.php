<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Database\Factories\UserFactory;
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
        $this->call(NcsbElementSeeder::class);

        Setting::store(Setting::OrganisationName, 'Your Organisation');

        if (app()->environment('local')) {
            $this->seedDemoUsers();
        }
    }

    /**
     * One account per role for local development. Password: see UserFactory::Password.
     */
    private function seedDemoUsers(): void
    {
        $accounts = [
            ['admin', 'System Administrator', 'admin@test.com'],
            ['complianceOfficer', 'Compliance Officer', 'officer@nacsa-toolkit.test'],
            ['executiveReviewer', 'Executive Reviewer', 'executive@nacsa-toolkit.test'],
        ];

        foreach ($accounts as [$state, $name, $email]) {
            if (User::query()->where('email', $email)->doesntExist()) {
                User::factory()->{$state}()->create(['name' => $name, 'email' => $email]);
            }
        }

        $this->command?->info('Demo users seeded with password: ' . UserFactory::Password);
    }
}
