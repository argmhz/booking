<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Company;
use App\Services\BookingLifecycleService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(private readonly BookingLifecycleService $bookingLifecycleService)
    {
    }

    public function index(Request $request): Response
    {
        $this->bookingLifecycleService->syncExecutedBookings();

        $user = $request->user();

        $companyIds = $user
            ->companies()
            ->pluck('companies.id')
            ->all();

        if ($companyIds === []) {
            $fallbackCompanyId = Company::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $user?->email)])
                ->value('id');

            if ($fallbackCompanyId) {
                $user?->companies()->syncWithoutDetaching([$fallbackCompanyId]);
                $companyIds = [$fallbackCompanyId];
            }
        }

        $bookings = $this->fetchBookingsForCompanies($companyIds);

        return Inertia::render('Company/Bookings/Index', [
            'bookings' => $bookings,
            'preview_mode' => false,
            'preview_company' => null,
        ]);
    }

    public function preview(Company $company): Response
    {
        $this->bookingLifecycleService->syncExecutedBookings();

        $bookings = $this->fetchBookingsForCompanies([$company->id]);

        return Inertia::render('Company/Bookings/Index', [
            'bookings' => $bookings,
            'preview_mode' => true,
            'preview_company' => [
                'id' => $company->id,
                'name' => $company->name,
            ],
        ]);
    }

    /**
     * @param  array<int, int>  $companyIds
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function fetchBookingsForCompanies(array $companyIds)
    {
        if ($companyIds === []) {
            return collect();
        }

        return Booking::query()
            ->whereIn('company_id', $companyIds)
            ->select([
                'id',
                'company_id',
                'company_address_id',
                'title',
                'description',
                'starts_at',
                'ends_at',
                'required_workers',
                'status',
                'show_employee_names_to_company',
                'approved_at',
                'executed_at',
                'is_invoiced',
                'is_paid',
            ])
            ->with('company:id,name')
            ->with('companyAddress:id,company_id,label,address_line_1,address_line_2,postal_code,city,country')
            ->with([
                'assignments' => function ($query): void {
                    $query->select(['id', 'booking_id', 'employee_user_id', 'status'])
                        ->where('status', 'assigned')
                        ->with('employee:id,name');
                },
            ])
            ->orderByDesc('starts_at')
            ->limit(500)
            ->get()
            ->map(function (Booking $booking): array {
                $assignedCount = $booking->assignments->count();

                return [
                    'id' => $booking->id,
                    'title' => $booking->title,
                    'description' => $booking->description,
                    'starts_at' => $booking->starts_at,
                    'ends_at' => $booking->ends_at,
                    'status' => $booking->status,
                    'workflow_status' => $booking->workflow_status,
                    'approved_at' => $booking->approved_at,
                    'executed_at' => $booking->executed_at,
                    'is_invoiced' => (bool) $booking->is_invoiced,
                    'is_paid' => (bool) $booking->is_paid,
                    'required_workers' => $booking->required_workers,
                    'assigned_workers' => $assignedCount,
                    'company' => $booking->company ? [
                        'id' => $booking->company->id,
                        'name' => $booking->company->name,
                    ] : null,
                    'company_address' => $booking->companyAddress ? [
                        'id' => $booking->companyAddress->id,
                        'label' => $booking->companyAddress->label,
                        'formatted' => trim(implode(', ', array_filter([
                            $booking->companyAddress->address_line_1,
                            $booking->companyAddress->address_line_2,
                            trim(($booking->companyAddress->postal_code ?? '').' '.$booking->companyAddress->city),
                            $booking->companyAddress->country,
                        ]))),
                    ] : null,
                    'employees' => $booking->show_employee_names_to_company
                        ? $booking->assignments
                            ->map(fn ($assignment): array => [
                                'id' => $assignment->employee?->id,
                                'name' => $assignment->employee?->name ?? 'Ukendt',
                            ])
                            ->values()
                            ->all()
                        : [],
                    'show_employee_names_to_company' => (bool) $booking->show_employee_names_to_company,
                ];
            })
            ->values();
    }
}
