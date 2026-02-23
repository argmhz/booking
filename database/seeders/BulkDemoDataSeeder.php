<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BulkDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndAdminSeeder::class,
        ]);

        $skills = collect(range(1, 10))
            ->map(function (int $index): Skill {
                return Skill::firstOrCreate(
                    ['name' => sprintf('Kompetence %02d', $index)],
                    ['description' => "Autogenereret kompetence {$index}"],
                );
            });

        foreach (range(1, 100) as $index) {
            Company::firstOrCreate(
                ['cvr' => sprintf('%08d', 70000000 + $index)],
                [
                    'name' => sprintf('Virksomhed %03d ApS', $index),
                    'email' => sprintf('company%03d@example.test', $index),
                    'phone' => sprintf('+45%08d', 20000000 + $index),
                    'is_active' => true,
                ],
            );
        }

        foreach (range(1, 50) as $index) {
            $email = sprintf('employee%03d@example.test', $index);

            $employee = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => sprintf('Medarbejder %03d', $index),
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'locale' => 'da',
                    'is_active' => true,
                ],
            );

            $employee->assignRole('employee');

            $profile = EmployeeProfile::firstOrCreate(
                ['user_id' => $employee->id],
                [
                    'phone' => sprintf('+45%08d', 30000000 + $index),
                    'hourly_wage' => 145 + ($index % 30),
                    'hourly_customer_rate' => 260 + ($index % 40),
                    'is_active' => true,
                ],
            );

            $skillIds = $skills
                ->shuffle()
                ->take(random_int(2, 5))
                ->pluck('id')
                ->all();

            $profile->skills()->syncWithoutDetaching($skillIds);
        }
    }
}

