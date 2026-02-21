<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Services\BookingLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct(private readonly BookingLifecycleService $bookingLifecycleService)
    {
    }

    public function index(): Response
    {
        $this->bookingLifecycleService->syncExecutedBookings();

        return Inertia::render('Admin/Finance/Index', [
            'bookings' => $this->buildFinanceBookings(),
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $this->bookingLifecycleService->syncExecutedBookings();

        $bookings = $this->buildFinanceBookings();
        $fileName = 'oekonomi-bookinger-'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        return response()->streamDownload(function () use ($bookings): void {
            $handle = fopen('php://output', 'wb');

            if (! $handle) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Booking ID',
                'Titel',
                'Virksomhed',
                'Adresse',
                'Start',
                'Slut',
                'Eksekveret',
                'Faktureret',
                'Løn udbetalt',
                'Løn total',
                'Pris total',
                'Margin total',
            ], ';');

            foreach ($bookings as $booking) {
                fputcsv($handle, [
                    $booking['id'],
                    $booking['title'],
                    $booking['company']['name'] ?? '',
                    $booking['company_address']['formatted'] ?? '',
                    $booking['starts_at'],
                    $booking['ends_at'],
                    $booking['executed_at'],
                    $booking['is_invoiced'] ? 'Ja' : 'Nej',
                    $booking['is_paid'] ? 'Ja' : 'Nej',
                    $booking['totals']['wage_total'],
                    $booking['totals']['price_total'],
                    $booking['totals']['margin_total'],
                ], ';');
            }

            fclose($handle);
        }, $fileName, $headers);
    }

    public function exportLinesCsv(): StreamedResponse
    {
        $this->bookingLifecycleService->syncExecutedBookings();

        $bookings = $this->buildFinanceBookings();
        $fileName = 'oekonomi-linjer-'.now()->format('Y-m-d_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        return response()->streamDownload(function () use ($bookings): void {
            $handle = fopen('php://output', 'wb');

            if (! $handle) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Booking ID',
                'Titel',
                'Virksomhed',
                'Adresse',
                'Medarbejder',
                'Medarbejder Email',
                'Timer',
                'Løn total',
                'Pris total',
                'Margin',
                'Faktureret',
                'Løn udbetalt',
            ], ';');

            foreach ($bookings as $booking) {
                foreach ($booking['lines'] as $line) {
                    fputcsv($handle, [
                        $booking['id'],
                        $booking['title'],
                        $booking['company']['name'] ?? '',
                        $booking['company_address']['formatted'] ?? '',
                        $line['employee']['name'] ?? '',
                        $line['employee']['email'] ?? '',
                        $line['hours_worked'],
                        $line['wage_total'],
                        $line['price_total'],
                        $line['margin_total'],
                        $booking['is_invoiced'] ? 'Ja' : 'Nej',
                        $booking['is_paid'] ? 'Ja' : 'Nej',
                    ], ';');
                }
            }

            fclose($handle);
        }, $fileName, $headers);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildFinanceBookings(): Collection
    {
        return Booking::query()
            ->whereNotNull('executed_at')
            ->select([
                'id',
                'company_id',
                'company_address_id',
                'title',
                'starts_at',
                'ends_at',
                'executed_at',
                'is_invoiced',
                'is_paid',
            ])
            ->with('company:id,name')
            ->with('companyAddress:id,company_id,label,address_line_1,address_line_2,postal_code,city,country')
            ->with([
                'assignments' => function ($query): void {
                    $query->select(['id', 'booking_id', 'employee_user_id', 'status', 'worker_rate', 'customer_rate'])
                        ->where('status', 'assigned')
                        ->with('employee:id,name,email');
                },
                'timesheets' => function ($query): void {
                    $query->select(['id', 'booking_id', 'employee_user_id', 'hours_worked', 'hourly_wage', 'hourly_price', 'wage_total', 'price_total', 'status']);
                },
            ])
            ->orderByDesc('ends_at')
            ->limit(300)
            ->get()
            ->map(function (Booking $booking): array {
                $timesheetsByEmployee = $booking->timesheets->keyBy('employee_user_id');
                $bookingHours = $booking->starts_at && $booking->ends_at
                    ? max(0, round($booking->starts_at->diffInMinutes($booking->ends_at, false) / 60, 2))
                    : 0.0;

                $lines = $booking->assignments
                    ->map(function (BookingAssignment $assignment) use ($timesheetsByEmployee, $bookingHours): array {
                        $timesheet = $timesheetsByEmployee->get($assignment->employee_user_id);
                        $hoursWorked = $timesheet?->hours_worked !== null ? (float) $timesheet->hours_worked : $bookingHours;

                        $wageTotal = $timesheet?->wage_total !== null
                            ? (float) $timesheet->wage_total
                            : ($assignment->worker_rate !== null ? round($hoursWorked * (float) $assignment->worker_rate, 2) : 0.0);

                        $priceTotal = $timesheet?->price_total !== null
                            ? (float) $timesheet->price_total
                            : ($assignment->customer_rate !== null ? round($hoursWorked * (float) $assignment->customer_rate, 2) : 0.0);

                        return [
                            'assignment_id' => $assignment->id,
                            'employee' => $assignment->employee ? [
                                'id' => $assignment->employee->id,
                                'name' => $assignment->employee->name,
                                'email' => $assignment->employee->email,
                            ] : null,
                            'hours_worked' => round($hoursWorked, 2),
                            'wage_total' => round($wageTotal, 2),
                            'price_total' => round($priceTotal, 2),
                            'margin_total' => round($priceTotal - $wageTotal, 2),
                        ];
                    })
                    ->values();

                $wageTotal = round((float) $lines->sum('wage_total'), 2);
                $priceTotal = round((float) $lines->sum('price_total'), 2);

                return [
                    'id' => $booking->id,
                    'title' => $booking->title,
                    'starts_at' => $booking->starts_at,
                    'ends_at' => $booking->ends_at,
                    'executed_at' => $booking->executed_at,
                    'workflow_status' => $booking->workflow_status,
                    'is_invoiced' => (bool) $booking->is_invoiced,
                    'is_paid' => (bool) $booking->is_paid,
                    'can_mark_invoiced' => $booking->canBeInvoiced(),
                    'can_unmark_invoiced' => $booking->canInvoiceBeRemoved(),
                    'can_mark_paid' => $booking->canBePaid(),
                    'can_unmark_paid' => $booking->canPaymentBeRemoved(),
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
                    'totals' => [
                        'wage_total' => $wageTotal,
                        'price_total' => $priceTotal,
                        'margin_total' => round($priceTotal - $wageTotal, 2),
                    ],
                    'lines' => $lines,
                ];
            })
            ->values();
    }

    public function markInvoiced(Booking $booking): RedirectResponse
    {
        if (! $booking->canBeInvoiced()) {
            return back()->withErrors([
                'booking' => 'Bookingen kan ikke markeres som faktureret i dens nuværende status.',
            ]);
        }

        $booking->update(['is_invoiced' => true]);

        return back()->with('status', 'Booking markeret som faktureret.');
    }

    public function unmarkInvoiced(Booking $booking): RedirectResponse
    {
        if (! $booking->canInvoiceBeRemoved()) {
            return back()->withErrors([
                'booking' => 'Fakturering kan ikke fjernes, når booking er markeret som betalt.',
            ]);
        }

        $booking->update(['is_invoiced' => false]);

        return back()->with('status', 'Faktura-markering fjernet.');
    }

    public function markPaid(Booking $booking): RedirectResponse
    {
        if (! $booking->canBePaid()) {
            return back()->withErrors([
                'booking' => 'Bookingen skal være faktureret og eksekveret før den kan markeres som betalt.',
            ]);
        }

        $booking->update([
            'is_invoiced' => true,
            'is_paid' => true,
        ]);

        return back()->with('status', 'Booking markeret som løn udbetalt.');
    }

    public function unmarkPaid(Booking $booking): RedirectResponse
    {
        if (! $booking->canPaymentBeRemoved()) {
            return back()->withErrors([
                'booking' => 'Kun betalte bookinger kan få fjernet betalingsmarkering.',
            ]);
        }

        $booking->update(['is_paid' => false]);

        return back()->with('status', 'Løn-markering fjernet.');
    }
}
