<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\Skill;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BulkDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndAdminSeeder::class,
        ]);

        $faker = FakerFactory::create('da_DK');
        $faker->seed(20260223);

        $skillCatalog = [
            ['name' => 'Truckcertifikat', 'description' => 'Erfaring med lagerkørsel og sikker håndtering af truck.'],
            ['name' => 'Stilladsarbejde', 'description' => 'Kan arbejde sikkert i højden og håndtere stilladsopgaver.'],
            ['name' => 'Rengøring Industri', 'description' => 'Industrirengøring i produktion og lagerområder.'],
            ['name' => 'Kundeservice', 'description' => 'Erfaring med kundekontakt, modtagelse og service.'],
            ['name' => 'Kørekort B', 'description' => 'Gyldigt kørekort B og erfaring med varebil.'],
            ['name' => 'Svejsning MIG/MAG', 'description' => 'Praktisk erfaring med svejseopgaver og kvalitetssikring.'],
            ['name' => 'Pak & Pluk', 'description' => 'Pakning, plukning og scanning i lagerstyring.'],
            ['name' => 'Byggeplads Sikkerhed', 'description' => 'Kendskab til sikkerhedsprocedurer på byggeplads.'],
            ['name' => 'Elektrisk Montør', 'description' => 'Montering og fejlfinding på lettere el-installationer.'],
            ['name' => 'Event Crew', 'description' => 'Opsætning, nedtagning og afvikling af events.'],
        ];

        $skills = collect($skillCatalog)->map(function (array $skillData): Skill {
            return Skill::updateOrCreate(
                ['name' => $skillData['name']],
                ['description' => $skillData['description']],
            );
        });

        $skillIds = $skills->pluck('id')->all();

        foreach (range(1, 100) as $index) {
            $companyName = rtrim($faker->company(), '.');
            $companySlug = Str::slug($companyName);

            $company = Company::updateOrCreate(
                ['cvr' => sprintf('%08d', 50000000 + $index)],
                [
                    'name' => $companyName,
                    'email' => "{$companySlug}-{$index}@example.test",
                    'phone' => '+45'.preg_replace('/\D+/', '', $faker->numerify('########')),
                    'is_active' => true,
                ],
            );

            $addressCount = $faker->numberBetween(1, 3);
            $addressLabels = ['Hovedadresse', 'Lager', 'Lokation 3'];
            $activeLabels = array_slice($addressLabels, 0, $addressCount);

            $company->addresses()
                ->whereNotIn('label', $activeLabels)
                ->delete();

            foreach ($activeLabels as $addressIndex => $label) {
                $company->addresses()->updateOrCreate(
                    ['label' => $label],
                    [
                        'address_line_1' => $faker->streetAddress(),
                        'address_line_2' => $faker->boolean(30)
                            ? sprintf('%d. %s', $faker->numberBetween(1, 5), $faker->randomElement(['tv', 'th', 'mf']))
                            : null,
                        'postal_code' => $faker->postcode(),
                        'city' => $faker->city(),
                        'country' => 'Danmark',
                        'is_default' => $addressIndex === 0,
                    ],
                );
            }
        }

        foreach (range(1, 50) as $index) {
            $fullName = $faker->name();
            $emailSlug = Str::slug($fullName);
            $email = "{$emailSlug}.{$index}@example.test";

            $employee = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $fullName,
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'locale' => 'da',
                    'is_active' => true,
                ],
            );

            $employee->assignRole('employee');

            $profile = EmployeeProfile::updateOrCreate(
                ['user_id' => $employee->id],
                [
                    'phone' => '+45'.preg_replace('/\D+/', '', $faker->numerify('########')),
                    'hourly_wage' => $faker->numberBetween(145, 220),
                    'hourly_customer_rate' => $faker->numberBetween(260, 380),
                    'is_active' => true,
                ],
            );

            $assignedSkillIds = $faker->randomElements(
                $skillIds,
                $faker->numberBetween(2, min(5, count($skillIds)))
            );

            $profile->skills()->sync($assignedSkillIds);
        }
    }
}
