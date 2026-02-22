<?php

use App\Models\Booking;
use App\Models\Company;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutMiddleware(ValidateCsrfToken::class);

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('employee', 'web');
});

function makeFinanceAdmin(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function makeFinanceEmployee(): User
{
    $employee = User::factory()->create();
    $employee->assignRole('employee');

    return $employee;
}

function makeFinanceBooking(Company $company, User $admin, array $overrides = []): Booking
{
    return Booking::create(array_merge([
        'company_id' => $company->id,
        'created_by' => $admin->id,
        'title' => 'Finance booking',
        'description' => 'Finance flow test',
        'starts_at' => now()->subHours(6),
        'ends_at' => now()->subHours(2),
        'required_workers' => 1,
        'assignment_mode' => 'first_come_first_served',
        'status' => 'completed',
        'approved_at' => now()->subHours(7),
        'approved_by' => $admin->id,
        'executed_at' => now()->subHours(1),
        'executed_by' => $admin->id,
        'is_invoiced' => true,
        'is_paid' => false,
    ], $overrides));
}

test('admin can approve and reopen a timesheet from finance flow', function (): void {
    $admin = makeFinanceAdmin();
    $employee = makeFinanceEmployee();
    $company = Company::create(['name' => 'Finance Co']);
    $booking = makeFinanceBooking($company, $admin);

    $timesheet = Timesheet::create([
        'booking_id' => $booking->id,
        'employee_user_id' => $employee->id,
        'hours_worked' => 4,
        'hourly_wage' => 200,
        'hourly_price' => 300,
        'wage_total' => 800,
        'price_total' => 1200,
        'status' => 'submitted',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.finance.timesheets.approve', $timesheet))
        ->assertSessionHasNoErrors();

    expect($timesheet->refresh()->status)->toBe('approved');

    $this->actingAs($admin)
        ->post(route('admin.finance.timesheets.reopen', $timesheet))
        ->assertSessionHasNoErrors();

    expect($timesheet->refresh()->status)->toBe('submitted');
});

test('booking cannot be marked paid when any timesheet is not approved', function (): void {
    $admin = makeFinanceAdmin();
    $employee = makeFinanceEmployee();
    $company = Company::create(['name' => 'Finance Co']);
    $booking = makeFinanceBooking($company, $admin);

    Timesheet::create([
        'booking_id' => $booking->id,
        'employee_user_id' => $employee->id,
        'hours_worked' => 4,
        'hourly_wage' => 200,
        'hourly_price' => 300,
        'wage_total' => 800,
        'price_total' => 1200,
        'status' => 'submitted',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.finance.bookings.mark-paid', $booking))
        ->assertSessionHasErrors('booking');

    expect($booking->refresh()->is_paid)->toBeFalse();
});

test('bulk paid marks only bookings with approved timesheets', function (): void {
    $admin = makeFinanceAdmin();
    $employee = makeFinanceEmployee();
    $company = Company::create(['name' => 'Finance Co']);

    $approvedBooking = makeFinanceBooking($company, $admin, ['title' => 'Approved booking']);
    $blockedBooking = makeFinanceBooking($company, $admin, ['title' => 'Blocked booking']);

    Timesheet::create([
        'booking_id' => $approvedBooking->id,
        'employee_user_id' => $employee->id,
        'hours_worked' => 4,
        'hourly_wage' => 200,
        'hourly_price' => 300,
        'wage_total' => 800,
        'price_total' => 1200,
        'status' => 'approved',
    ]);

    Timesheet::create([
        'booking_id' => $blockedBooking->id,
        'employee_user_id' => $employee->id,
        'hours_worked' => 4,
        'hourly_wage' => 200,
        'hourly_price' => 300,
        'wage_total' => 800,
        'price_total' => 1200,
        'status' => 'submitted',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.finance.bookings.bulk-mark-paid'), [
            'booking_ids' => [$approvedBooking->id, $blockedBooking->id],
        ])
        ->assertSessionHasNoErrors();

    expect($approvedBooking->refresh()->is_paid)->toBeTrue();
    expect($blockedBooking->refresh()->is_paid)->toBeFalse();
});
