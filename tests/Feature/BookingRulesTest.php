<?php

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingWaitlist;
use App\Models\Company;
use App\Models\CompanyAddress;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\BookingLifecycleService;
use App\Services\BookingStaffingService;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('employee', 'web');
});

function makeAdminUser(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function makeEmployeeUser(string $email, float $hourlyWage = 120.0, float $hourlyCustomerRate = 220.0): User
{
    $employee = User::factory()->create([
        'email' => $email,
        'is_active' => true,
    ]);
    $employee->assignRole('employee');

    EmployeeProfile::create([
        'user_id' => $employee->id,
        'hourly_wage' => $hourlyWage,
        'hourly_customer_rate' => $hourlyCustomerRate,
        'is_active' => true,
    ]);

    return $employee;
}

function makeCompany(string $name = 'Test Company'): Company
{
    return Company::create([
        'name' => $name,
        'is_active' => true,
    ]);
}

function makeBooking(Company $company, User $admin, array $overrides = []): Booking
{
    return Booking::create(array_merge([
        'company_id' => $company->id,
        'created_by' => $admin->id,
        'title' => 'Dagvagt',
        'description' => 'Test booking',
        'starts_at' => Carbon::now()->addDay(),
        'ends_at' => Carbon::now()->addDay()->addHours(8),
        'required_workers' => 1,
        'assignment_mode' => 'first_come_first_served',
        'show_employee_names_to_company' => false,
        'status' => 'open',
    ], $overrides));
}

test('booking capacity assigns first employee and puts extra employee on waitlist', function (): void {
    $company = makeCompany();
    $admin = makeAdminUser();
    $employeeA = makeEmployeeUser('employee-a@example.test', 150, 250);
    $employeeB = makeEmployeeUser('employee-b@example.test', 160, 260);

    $booking = makeBooking($company, $admin, ['required_workers' => 1]);

    $staffingService = app(BookingStaffingService::class);

    $resultA = $staffingService->addEmployeeToBooking($booking, $employeeA->id);
    $resultB = $staffingService->addEmployeeToBooking($booking, $employeeB->id);

    expect($resultA)->toBe('assigned');
    expect($resultB)->toBe('waitlisted');

    expect(BookingAssignment::query()
        ->where('booking_id', $booking->id)
        ->where('status', 'assigned')
        ->count())->toBe(1);

    $waitlistEntry = BookingWaitlist::query()
        ->where('booking_id', $booking->id)
        ->whereNull('left_at')
        ->first();

    expect($waitlistEntry)->not->toBeNull();
    expect($waitlistEntry->employee_user_id)->toBe($employeeB->id);
    expect($waitlistEntry->position)->toBe(1);
});

test('waitlisted employee is promoted when an assignment is cancelled', function (): void {
    $company = makeCompany();
    $admin = makeAdminUser();
    $employeeA = makeEmployeeUser('employee-c@example.test', 150, 250);
    $employeeB = makeEmployeeUser('employee-d@example.test', 160, 260);

    $booking = makeBooking($company, $admin, ['required_workers' => 1]);

    $staffingService = app(BookingStaffingService::class);
    $staffingService->addEmployeeToBooking($booking, $employeeA->id);
    $staffingService->addEmployeeToBooking($booking, $employeeB->id);

    $assignmentA = BookingAssignment::query()
        ->where('booking_id', $booking->id)
        ->where('employee_user_id', $employeeA->id)
        ->where('status', 'assigned')
        ->firstOrFail();

    $staffingService->cancelAssignment($assignmentA);

    $promotedAssignment = BookingAssignment::query()
        ->where('booking_id', $booking->id)
        ->where('employee_user_id', $employeeB->id)
        ->where('status', 'assigned')
        ->first();

    expect($promotedAssignment)->not->toBeNull();

    expect(BookingWaitlist::query()
        ->where('booking_id', $booking->id)
        ->whereNull('left_at')
        ->count())->toBe(0);
});

test('approved booking is auto executed after end time has passed', function (): void {
    $company = makeCompany();
    $admin = makeAdminUser();

    $booking = makeBooking($company, $admin, [
        'starts_at' => Carbon::now()->subHours(6),
        'ends_at' => Carbon::now()->subHour(),
        'approved_at' => Carbon::now()->subHours(2),
        'approved_by' => $admin->id,
        'executed_at' => null,
        'status' => 'filled',
    ]);

    $updatedRows = app(BookingLifecycleService::class)->syncExecutedBookings();

    $booking->refresh();

    expect($updatedRows)->toBe(1);
    expect($booking->executed_at)->not->toBeNull();
    expect($booking->status)->toBe('completed');
});

test('admin can create booking with company address from same company', function (): void {
    $admin = makeAdminUser();
    $company = makeCompany('Company A');

    $address = CompanyAddress::create([
        'company_id' => $company->id,
        'label' => 'HQ',
        'address_line_1' => 'Main St 1',
        'city' => 'Copenhagen',
        'country' => 'Denmark',
        'is_default' => true,
    ]);

    $payload = [
        'company_id' => $company->id,
        'company_address_id' => $address->id,
        'title' => 'Aftenhold',
        'description' => 'Test',
        'starts_at' => Carbon::now()->addDay()->startOfHour()->toDateTimeString(),
        'ends_at' => Carbon::now()->addDay()->startOfHour()->addHours(4)->toDateTimeString(),
        'required_workers' => 2,
        'assignment_mode' => 'first_come_first_served',
        'show_employee_names_to_company' => false,
    ];

    $this->actingAs($admin)
        ->post(route('bookings.store'), $payload)
        ->assertRedirect(route('bookings.calendar'));

    $booking = Booking::query()->latest('id')->first();

    expect($booking)->not->toBeNull();
    expect($booking->company_id)->toBe($company->id);
    expect($booking->company_address_id)->toBe($address->id);
});

test('admin cannot create booking with address belonging to another company', function (): void {
    $admin = makeAdminUser();
    $companyA = makeCompany('Company A');
    $companyB = makeCompany('Company B');

    $foreignAddress = CompanyAddress::create([
        'company_id' => $companyB->id,
        'label' => 'Branch',
        'address_line_1' => 'Other St 2',
        'city' => 'Aarhus',
        'country' => 'Denmark',
        'is_default' => true,
    ]);

    $payload = [
        'company_id' => $companyA->id,
        'company_address_id' => $foreignAddress->id,
        'title' => 'Nattevagt',
        'description' => 'Test',
        'starts_at' => Carbon::now()->addDays(2)->startOfHour()->toDateTimeString(),
        'ends_at' => Carbon::now()->addDays(2)->startOfHour()->addHours(4)->toDateTimeString(),
        'required_workers' => 1,
        'assignment_mode' => 'first_come_first_served',
        'show_employee_names_to_company' => false,
    ];

    $this->actingAs($admin)
        ->from(route('bookings.create'))
        ->post(route('bookings.store'), $payload)
        ->assertRedirect(route('bookings.create'))
        ->assertSessionHasErrors('company_address_id');

    expect(Booking::query()->count())->toBe(0);
});
