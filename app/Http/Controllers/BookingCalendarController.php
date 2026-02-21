<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Company;
use App\Models\User;
use App\Services\BookingLifecycleService;
use Inertia\Inertia;
use Inertia\Response;

class BookingCalendarController extends Controller
{
    public function __construct(private readonly BookingLifecycleService $bookingLifecycleService)
    {
    }

    public function index(): Response
    {
        $this->bookingLifecycleService->syncExecutedBookings();

        $bookings = Booking::query()
            ->select([
                'id',
                'company_id',
                'company_address_id',
                'created_by',
                'created_at',
                'title',
                'description',
                'starts_at',
                'ends_at',
                'required_workers',
                'assignment_mode',
                'status',
                'show_employee_names_to_company',
                'closed_at',
                'closed_by',
                'approved_at',
                'approved_by',
                'executed_at',
                'executed_by',
                'is_invoiced',
                'is_paid',
            ])
            ->with('company:id,name')
            ->with('companyAddress:id,company_id,label,address_line_1,address_line_2,postal_code,city,country,is_default')
            ->with('creator:id,name')
            ->with('approver:id,name')
            ->with('executor:id,name')
            ->with('closer:id,name')
            ->with([
                'assignments' => function ($query): void {
                    $query->select(['id', 'booking_id', 'employee_user_id', 'status', 'assigned_at', 'cancelled_at', 'worker_rate', 'customer_rate'])
                        ->where('status', 'assigned')
                        ->with('employee:id,name,email');
                },
                'waitlistEntries' => function ($query): void {
                    $query->select(['id', 'booking_id', 'employee_user_id', 'position', 'joined_at', 'left_at'])
                        ->whereNull('left_at')
                        ->orderBy('position')
                        ->with('employee:id,name,email');
                },
            ])
            ->orderBy('starts_at')
            ->limit(300)
            ->get();

        return Inertia::render('Bookings/Calendar', [
            'bookings' => $bookings,
            'companies' => Company::query()
                ->select(['id', 'name'])
                ->with(['addresses' => fn ($query) => $query->select([
                    'id',
                    'company_id',
                    'label',
                    'address_line_1',
                    'address_line_2',
                    'postal_code',
                    'city',
                    'country',
                    'is_default',
                ])->orderByDesc('is_default')->orderBy('label')])
                ->orderBy('name')
                ->get(),
            'employees' => User::query()
                ->role('employee')
                ->where('is_active', true)
                ->select(['id', 'name', 'email'])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
