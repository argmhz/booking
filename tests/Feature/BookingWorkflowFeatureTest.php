<?php

use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Company;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\BookingRequestNotification;
use App\Notifications\BookingWaitlistNotification;
use App\Services\BookingStaffingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('employee', 'web');
    Role::findOrCreate('company', 'web');
});

function makeAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('admin');

    return $user;
}

function makeEmployee(string $email): User
{
    $user = User::factory()->create([
        'email' => $email,
        'is_active' => true,
    ]);
    $user->assignRole('employee');

    EmployeeProfile::create([
        'user_id' => $user->id,
        'hourly_wage' => 150,
        'hourly_customer_rate' => 250,
        'is_active' => true,
    ]);

    return $user;
}

function makeCompanyWithOptionalUser(?User $user = null, ?string $email = null): Company
{
    $company = Company::create([
        'name' => 'Company '.fake()->unique()->word(),
        'email' => $email,
        'is_active' => true,
    ]);

    if ($user) {
        $company->users()->syncWithoutDetaching([$user->id]);
    }

    return $company;
}

function makeBookingFor(Company $company, User $admin, array $overrides = []): Booking
{
    return Booking::create(array_merge([
        'company_id' => $company->id,
        'created_by' => $admin->id,
        'title' => 'Workflow test booking',
        'starts_at' => Carbon::now()->addDay(),
        'ends_at' => Carbon::now()->addDay()->addHours(8),
        'required_workers' => 1,
        'assignment_mode' => 'first_come_first_served',
        'show_employee_names_to_company' => false,
        'status' => 'open',
    ], $overrides));
}

test('admin can approve and revoke approval for non-executed booking', function (): void {
    $admin = makeAdmin();
    $company = makeCompanyWithOptionalUser();
    $booking = makeBookingFor($company, $admin);

    $this->actingAs($admin)
        ->post(route('bookings.approve', $booking))
        ->assertSessionHasNoErrors();

    $booking->refresh();
    expect($booking->approved_at)->not->toBeNull();
    expect($booking->approved_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->post(route('bookings.revoke-approval', $booking))
        ->assertSessionHasNoErrors();

    $booking->refresh();
    expect($booking->approved_at)->toBeNull();
    expect($booking->approved_by)->toBeNull();
    expect($booking->executed_at)->toBeNull();
});

test('executed booking is locked for update and request actions', function (): void {
    $admin = makeAdmin();
    $company = makeCompanyWithOptionalUser();
    $booking = makeBookingFor($company, $admin, [
        'approved_at' => Carbon::now()->subDay(),
        'approved_by' => $admin->id,
        'executed_at' => Carbon::now()->subHour(),
    ]);

    $this->actingAs($admin)
        ->patch(route('bookings.update', $booking), [])
        ->assertSessionHasErrors('booking');

    $this->actingAs($admin)
        ->post(route('bookings.requests.store', $booking), ['employee_user_ids' => []])
        ->assertSessionHasErrors('booking');
});

test('approval sends notifications to company users and assigned employees', function (): void {
    Notification::fake();

    $admin = makeAdmin();
    $companyUser = User::factory()->create();
    $companyUser->assignRole('company');
    $company = makeCompanyWithOptionalUser($companyUser);
    $employee = makeEmployee('approved-notify-employee@example.test');

    $booking = makeBookingFor($company, $admin);
    app(BookingStaffingService::class)->addEmployeeToBooking($booking, $employee->id);

    $this->actingAs($admin)
        ->post(route('bookings.approve', $booking))
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($companyUser, BookingApprovedNotification::class);
    Notification::assertSentTo($employee, BookingApprovedNotification::class);
});

test('request notifications are sent and accepted overflow goes to waitlist with notification', function (): void {
    Notification::fake();

    $admin = makeAdmin();
    $company = makeCompanyWithOptionalUser();
    $employeeA = makeEmployee('request-a@example.test');
    $employeeB = makeEmployee('request-b@example.test');

    $booking = makeBookingFor($company, $admin, [
        'required_workers' => 1,
        'approved_at' => Carbon::now()->subHour(),
        'approved_by' => $admin->id,
    ]);

    app(BookingStaffingService::class)->addEmployeeToBooking($booking, $employeeA->id);

    $this->actingAs($admin)
        ->post(route('bookings.requests.store', $booking), [
            'employee_user_ids' => [$employeeB->id],
        ])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo($employeeB, BookingRequestNotification::class);

    $request = BookingRequest::query()
        ->where('booking_id', $booking->id)
        ->where('employee_user_id', $employeeB->id)
        ->firstOrFail();

    $this->actingAs($employeeB)
        ->post(route('employee.requests.respond', $request), ['response' => 'accepted'])
        ->assertSessionHasNoErrors();

    $request->refresh();
    expect($request->status)->toBe('accepted');
    expect($booking->waitlistEntries()->where('employee_user_id', $employeeB->id)->whereNull('left_at')->exists())->toBeTrue();
    Notification::assertSentTo($employeeB, BookingWaitlistNotification::class);
});

test('company user only sees own bookings and fallback email link works', function (): void {
    $admin = makeAdmin();

    $companyUser = User::factory()->create(['email' => 'company-owner@example.test']);
    $companyUser->assignRole('company');
    $companyA = makeCompanyWithOptionalUser($companyUser);
    $companyB = makeCompanyWithOptionalUser();

    $bookingA = makeBookingFor($companyA, $admin, ['title' => 'Booking A']);
    makeBookingFor($companyB, $admin, ['title' => 'Booking B']);

    $this->actingAs($companyUser)
        ->get(route('company.bookings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Company/Bookings/Index')
            ->has('bookings', 1)
            ->where('bookings.0.id', $bookingA->id)
        );

    $fallbackUser = User::factory()->create(['email' => 'fallback-company@example.test']);
    $fallbackUser->assignRole('company');
    $fallbackCompany = makeCompanyWithOptionalUser(null, 'fallback-company@example.test');
    $fallbackBooking = makeBookingFor($fallbackCompany, $admin, ['title' => 'Fallback Booking']);

    expect($fallbackCompany->users()->where('users.id', $fallbackUser->id)->exists())->toBeFalse();

    $this->actingAs($fallbackUser)
        ->get(route('company.bookings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Company/Bookings/Index')
            ->has('bookings', 1)
            ->where('bookings.0.id', $fallbackBooking->id)
        );

    $fallbackCompany->refresh();
    expect($fallbackCompany->users()->where('users.id', $fallbackUser->id)->exists())->toBeTrue();
});
