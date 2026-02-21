<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrCreate(
            ['name' => 'Demo Virksomhed ApS (Seed)'],
            ['email' => 'kontakt+seed@demo-virksomhed.dk', 'is_active' => true],
        );

        $companyUser = User::firstOrCreate(
            ['email' => 'company@example.com'],
            [
                'name' => 'Company User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'locale' => 'da',
            ],
        );
        $companyUser->assignRole('company');

        $company->users()->syncWithoutDetaching([$companyUser->id]);

        for ($i = 1; $i <= 3; $i++) {
            $employee = User::firstOrCreate(
                ['email' => "employee{$i}@example.com"],
                [
                    'name' => "Medarbejder {$i}",
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'locale' => 'da',
                ],
            );

            $employee->assignRole('employee');
        }
    }
}
