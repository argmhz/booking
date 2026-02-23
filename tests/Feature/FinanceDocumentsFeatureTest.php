<?php

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\Company;
use App\Models\FinanceDocument;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->withoutMiddleware(ValidateCsrfToken::class);

    Role::findOrCreate('admin', 'web');
    Role::findOrCreate('employee', 'web');
});

function makeAdminForFinanceDocs(): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

function makeEmployeeForFinanceDocs(): User
{
    $employee = User::factory()->create();
    $employee->assignRole('employee');

    return $employee;
}

function makeExecutedBookingForFinanceDocs(Company $company, User $admin, array $overrides = []): Booking
{
    return Booking::create(array_merge([
        'company_id' => $company->id,
        'created_by' => $admin->id,
        'title' => 'Finance docs booking',
        'description' => 'Test booking',
        'starts_at' => now()->subHours(8),
        'ends_at' => now()->subHours(4),
        'required_workers' => 1,
        'assignment_mode' => 'first_come_first_served',
        'status' => 'completed',
        'approved_at' => now()->subHours(9),
        'approved_by' => $admin->id,
        'executed_at' => now()->subHours(3),
        'executed_by' => $admin->id,
        'is_invoiced' => false,
        'is_paid' => false,
    ], $overrides));
}

test('admin can create and finalize invoice draft from selected bookings', function (): void {
    $admin = makeAdminForFinanceDocs();
    $employee = makeEmployeeForFinanceDocs();
    $company = Company::create(['name' => 'Invoice Co']);
    $booking = makeExecutedBookingForFinanceDocs($company, $admin);

    BookingAssignment::create([
        'booking_id' => $booking->id,
        'employee_user_id' => $employee->id,
        'status' => 'assigned',
        'assigned_at' => now()->subHours(7),
        'worker_rate' => 200,
        'customer_rate' => 320,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.finance.documents.store-invoice-draft'), [
            'booking_ids' => [$booking->id],
        ])
        ->assertSessionHasNoErrors();

    $document = FinanceDocument::query()->latest('id')->first();

    expect($document)->not->toBeNull();
    expect($document->type)->toBe('invoice');
    expect($document->status)->toBe('draft');
    expect($document->lines()->count())->toBe(1);

    $this->actingAs($admin)
        ->post(route('admin.finance.documents.finalize', $document))
        ->assertSessionHasNoErrors();

    expect($document->fresh()->status)->toBe('finalized');
    expect($booking->fresh()->is_invoiced)->toBeTrue();
});

test('payroll draft only includes payable bookings and finalization marks paid', function (): void {
    $admin = makeAdminForFinanceDocs();
    $employee = makeEmployeeForFinanceDocs();
    $company = Company::create(['name' => 'Payroll Co']);

    $eligible = makeExecutedBookingForFinanceDocs($company, $admin, [
        'title' => 'Eligible',
        'is_invoiced' => true,
    ]);

    $blocked = makeExecutedBookingForFinanceDocs($company, $admin, [
        'title' => 'Blocked',
        'is_invoiced' => true,
    ]);

    foreach ([$eligible, $blocked] as $booking) {
        BookingAssignment::create([
            'booking_id' => $booking->id,
            'employee_user_id' => $employee->id,
            'status' => 'assigned',
            'assigned_at' => now()->subHours(7),
            'worker_rate' => 210,
            'customer_rate' => 340,
        ]);
    }

    Timesheet::create([
        'booking_id' => $eligible->id,
        'employee_user_id' => $employee->id,
        'hours_worked' => 4,
        'hourly_wage' => 210,
        'hourly_price' => 340,
        'wage_total' => 840,
        'price_total' => 1360,
        'status' => 'approved',
    ]);

    Timesheet::create([
        'booking_id' => $blocked->id,
        'employee_user_id' => $employee->id,
        'hours_worked' => 4,
        'hourly_wage' => 210,
        'hourly_price' => 340,
        'wage_total' => 840,
        'price_total' => 1360,
        'status' => 'submitted',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.finance.documents.store-payroll-draft'), [
            'booking_ids' => [$eligible->id, $blocked->id],
        ])
        ->assertSessionHasNoErrors();

    $document = FinanceDocument::query()->latest('id')->first();

    expect($document)->not->toBeNull();
    expect($document->type)->toBe('payroll');
    expect($document->lines()->distinct('booking_id')->count('booking_id'))->toBe(1);

    $this->actingAs($admin)
        ->post(route('admin.finance.documents.finalize', $document))
        ->assertSessionHasNoErrors();

    expect($document->fresh()->status)->toBe('finalized');
    expect($eligible->fresh()->is_paid)->toBeTrue();
    expect($blocked->fresh()->is_paid)->toBeFalse();
});

