<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BookingCalendarController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Admin\BookingManagementController as AdminBookingManagementController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\EmployeeController as AdminEmployeeController;
use App\Http\Controllers\Admin\AdminController as AdminAdminController;
use App\Http\Controllers\Admin\SkillController as AdminSkillController;
use App\Http\Controllers\Admin\BookingRequestController as AdminBookingRequestController;
use App\Http\Controllers\Admin\BookingStaffingController as AdminBookingStaffingController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\Employee\BookingRequestController as EmployeeBookingRequestController;
use App\Http\Controllers\Company\BookingController as CompanyBookingController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    $user = request()->user();

    if ($user && ! $user->password) {
        return redirect()->route('password.setup.edit');
    }

    if ($user?->hasRole('employee')) {
        return redirect()->route('employee.requests.index');
    }

    if ($user?->hasRole('company')) {
        return redirect()->route('company.bookings.index');
    }

    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'password.set'])->group(function () {
    Route::get('/bookings/calendar', [BookingCalendarController::class, 'index'])
        ->middleware('role:admin')
        ->name('bookings.calendar');
    Route::get('/bookings/create', [AdminBookingManagementController::class, 'create'])
        ->middleware('role:admin')
        ->name('bookings.create');
    Route::post('/bookings', [AdminBookingManagementController::class, 'store'])
        ->middleware('role:admin')
        ->name('bookings.store');
    Route::patch('/bookings/{booking}', [AdminBookingManagementController::class, 'update'])
        ->middleware('role:admin')
        ->name('bookings.update');
    Route::delete('/bookings/{booking}', [AdminBookingManagementController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('bookings.destroy');
    Route::post('/bookings/{booking}/approve', [AdminBookingManagementController::class, 'approve'])
        ->middleware('role:admin')
        ->name('bookings.approve');
    Route::post('/bookings/{booking}/revoke-approval', [AdminBookingManagementController::class, 'revokeApproval'])
        ->middleware('role:admin')
        ->name('bookings.revoke-approval');
    Route::post('/bookings/{booking}/requests', [AdminBookingRequestController::class, 'store'])
        ->middleware('role:admin')
        ->name('bookings.requests.store');
    Route::post('/bookings/{booking}/employees', [AdminBookingManagementController::class, 'addEmployee'])
        ->middleware('role:admin')
        ->name('bookings.employees.add');
    Route::delete('/bookings/{booking}/assignments/{assignment}', [AdminBookingStaffingController::class, 'cancelAssignment'])
        ->middleware('role:admin')
        ->name('bookings.assignments.cancel');
    Route::patch('/bookings/{booking}/assignments/{assignment}/rates', [AdminBookingStaffingController::class, 'updateAssignmentRates'])
        ->middleware('role:admin')
        ->name('bookings.assignments.rates.update');
    Route::delete('/bookings/{booking}/waitlist/{waitlistEntry}', [AdminBookingManagementController::class, 'removeWaitlistEntry'])
        ->middleware('role:admin')
        ->name('bookings.waitlist.remove');
    Route::post('/bookings/{booking}/waitlist/{waitlistEntry}/promote', [AdminBookingStaffingController::class, 'promoteWaitlistEntry'])
        ->middleware('role:admin')
        ->name('bookings.waitlist.promote');
    Route::get('/admin/companies', [AdminCompanyController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.companies.index');
    Route::post('/admin/companies', [AdminCompanyController::class, 'store'])
        ->middleware('role:admin')
        ->name('admin.companies.store');
    Route::patch('/admin/companies/{company}', [AdminCompanyController::class, 'update'])
        ->middleware('role:admin')
        ->name('admin.companies.update');
    Route::patch('/admin/companies/{company}/active', [AdminCompanyController::class, 'toggleActive'])
        ->middleware('role:admin')
        ->name('admin.companies.toggle-active');
    Route::post('/admin/companies/{company}/users', [AdminCompanyController::class, 'attachUser'])
        ->middleware('role:admin')
        ->name('admin.companies.users.attach');
    Route::delete('/admin/companies/{company}/users/{user}', [AdminCompanyController::class, 'detachUser'])
        ->middleware('role:admin')
        ->name('admin.companies.users.detach');
    Route::delete('/admin/companies/{company}', [AdminCompanyController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('admin.companies.destroy');
    Route::get('/admin/companies/{company}/bookings-preview', [CompanyBookingController::class, 'preview'])
        ->middleware('role:admin')
        ->name('admin.companies.bookings-preview');
    Route::get('/admin/employees', [AdminEmployeeController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.employees.index');
    Route::post('/admin/employees', [AdminEmployeeController::class, 'store'])
        ->middleware('role:admin')
        ->name('admin.employees.store');
    Route::patch('/admin/employees/{employee}', [AdminEmployeeController::class, 'update'])
        ->middleware('role:admin')
        ->name('admin.employees.update');
    Route::patch('/admin/employees/{employee}/active', [AdminEmployeeController::class, 'toggleActive'])
        ->middleware('role:admin')
        ->name('admin.employees.toggle-active');
    Route::delete('/admin/employees/{employee}', [AdminEmployeeController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('admin.employees.destroy');
    Route::get('/admin/admins', [AdminAdminController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.admins.index');
    Route::post('/admin/admins', [AdminAdminController::class, 'store'])
        ->middleware('role:admin')
        ->name('admin.admins.store');
    Route::patch('/admin/admins/{admin}', [AdminAdminController::class, 'update'])
        ->middleware('role:admin')
        ->name('admin.admins.update');
    Route::patch('/admin/admins/{admin}/active', [AdminAdminController::class, 'toggleActive'])
        ->middleware('role:admin')
        ->name('admin.admins.toggle-active');
    Route::delete('/admin/admins/{admin}/role', [AdminAdminController::class, 'removeRole'])
        ->middleware('role:admin')
        ->name('admin.admins.remove-role');
    Route::post('/admin/skills', [AdminSkillController::class, 'store'])
        ->middleware('role:admin')
        ->name('admin.skills.store');
    Route::get('/admin/skills', [AdminSkillController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.skills.index');
    Route::patch('/admin/skills/{skill}', [AdminSkillController::class, 'update'])
        ->middleware('role:admin')
        ->name('admin.skills.update');
    Route::delete('/admin/skills/{skill}', [AdminSkillController::class, 'destroy'])
        ->middleware('role:admin')
        ->name('admin.skills.destroy');
    Route::get('/admin/finance', [AdminFinanceController::class, 'index'])
        ->middleware('role:admin')
        ->name('admin.finance.index');
    Route::get('/admin/finance/export/csv', [AdminFinanceController::class, 'exportCsv'])
        ->middleware('role:admin')
        ->name('admin.finance.export.csv');
    Route::get('/admin/finance/export/csv-lines', [AdminFinanceController::class, 'exportLinesCsv'])
        ->middleware('role:admin')
        ->name('admin.finance.export.csv-lines');
    Route::post('/admin/finance/bookings/{booking}/invoiced', [AdminFinanceController::class, 'markInvoiced'])
        ->middleware('role:admin')
        ->name('admin.finance.bookings.mark-invoiced');
    Route::post('/admin/finance/bookings/{booking}/unmark-invoiced', [AdminFinanceController::class, 'unmarkInvoiced'])
        ->middleware('role:admin')
        ->name('admin.finance.bookings.unmark-invoiced');
    Route::post('/admin/finance/bookings/{booking}/paid', [AdminFinanceController::class, 'markPaid'])
        ->middleware('role:admin')
        ->name('admin.finance.bookings.mark-paid');
    Route::post('/admin/finance/bookings/{booking}/unmark-paid', [AdminFinanceController::class, 'unmarkPaid'])
        ->middleware('role:admin')
        ->name('admin.finance.bookings.unmark-paid');

    Route::get('/employee/requests', [EmployeeBookingRequestController::class, 'index'])
        ->middleware('role:employee')
        ->name('employee.requests.index');
    Route::post('/employee/requests/{bookingRequest}/respond', [EmployeeBookingRequestController::class, 'respond'])
        ->middleware('role:employee')
        ->name('employee.requests.respond');
    Route::delete('/employee/waitlist/{bookingWaitlist}', [EmployeeBookingRequestController::class, 'leaveWaitlist'])
        ->middleware('role:employee')
        ->name('employee.waitlist.leave');
    Route::get('/company/bookings', [CompanyBookingController::class, 'index'])
        ->middleware('role:company')
        ->name('company.bookings.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');
});

require __DIR__.'/auth.php';
