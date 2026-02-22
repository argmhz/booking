<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\Timesheet;
use App\Services\BookingLifecycleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct(private readonly BookingLifecycleService $bookingLifecycleService)
    {
    }

    public function index(Request $request): Response
    {
        $this->bookingLifecycleService->syncExecutedBookings();
        $filters = $this->normalizeFilters($request);

        return Inertia::render('Admin/Finance/Index', [
            'bookings' => $this->buildFinanceBookings($filters),
            'filters' => $filters,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->bookingLifecycleService->syncExecutedBookings();

        $bookings = $this->buildFinanceBookings($this->normalizeFilters($request, 'all'));
        $fileName = 'oekonomi-bookinger-'.now()->format('Y-m-d_His').'.csv';

        return $this->streamCsv($fileName, [
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
        ], function ($handle) use ($bookings): void {
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
        });
    }

    public function exportLinesCsv(Request $request): StreamedResponse
    {
        $this->bookingLifecycleService->syncExecutedBookings();

        $bookings = $this->buildFinanceBookings($this->normalizeFilters($request, 'all'));
        $fileName = 'oekonomi-linjer-'.now()->format('Y-m-d_His').'.csv';

        return $this->streamCsv($fileName, [
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
            'Timesheet status',
            'Faktureret',
            'Løn udbetalt',
        ], function ($handle) use ($bookings): void {
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
                        $line['timesheet_status'] ?? 'calculated',
                        $booking['is_invoiced'] ? 'Ja' : 'Nej',
                        $booking['is_paid'] ? 'Ja' : 'Nej',
                    ], ';');
                }
            }
        });
    }

    public function exportInvoicesCsv(Request $request): StreamedResponse
    {
        $this->bookingLifecycleService->syncExecutedBookings();

        $selectedBookingIds = $this->selectedBookingIds($request);
        $bookings = $this->buildFinanceBookings($this->normalizeFilters($request, 'all'))
            ->whereIn('id', $selectedBookingIds)
            ->filter(fn (array $booking): bool => (bool) $booking['can_mark_invoiced'])
            ->values();

        $fileName = 'faktura-kladde-'.now()->format('Y-m-d_His').'.csv';

        return $this->streamCsv($fileName, [
            'Booking ID',
            'Titel',
            'Virksomhed',
            'Adresse',
            'Slut',
            'Pris total',
            'Løn total',
            'Margin',
        ], function ($handle) use ($bookings): void {
            foreach ($bookings as $booking) {
                fputcsv($handle, [
                    $booking['id'],
                    $booking['title'],
                    $booking['company']['name'] ?? '',
                    $booking['company_address']['formatted'] ?? '',
                    $booking['ends_at'],
                    $booking['totals']['price_total'],
                    $booking['totals']['wage_total'],
                    $booking['totals']['margin_total'],
                ], ';');
            }
        });
    }

    public function exportPayrollCsv(Request $request): StreamedResponse
    {
        $this->bookingLifecycleService->syncExecutedBookings();

        $selectedBookingIds = $this->selectedBookingIds($request);
        $bookings = $this->buildFinanceBookings($this->normalizeFilters($request, 'all'))
            ->whereIn('id', $selectedBookingIds)
            ->filter(fn (array $booking): bool => (bool) $booking['can_mark_paid'])
            ->values();

        $fileName = 'loen-kladde-'.now()->format('Y-m-d_His').'.csv';

        return $this->streamCsv($fileName, [
            'Booking ID',
            'Titel',
            'Virksomhed',
            'Medarbejder',
            'Email',
            'Timer',
            'Løn total',
            'Timesheet status',
        ], function ($handle) use ($bookings): void {
            foreach ($bookings as $booking) {
                foreach ($booking['lines'] as $line) {
                    fputcsv($handle, [
                        $booking['id'],
                        $booking['title'],
                        $booking['company']['name'] ?? '',
                        $line['employee']['name'] ?? '',
                        $line['employee']['email'] ?? '',
                        $line['hours_worked'],
                        $line['wage_total'],
                        $line['timesheet_status'] ?? 'calculated',
                    ], ';');
                }
            }
        });
    }

    public function bulkMarkInvoiced(Request $request): RedirectResponse
    {
        $selectedBookingIds = $this->selectedBookingIds($request);

        $bookings = Booking::query()
            ->whereIn('id', $selectedBookingIds)
            ->with(['timesheets:id,booking_id,status'])
            ->get();

        $updated = 0;

        foreach ($bookings as $booking) {
            if (! $booking->canBeInvoiced()) {
                continue;
            }

            $booking->update(['is_invoiced' => true]);
            $updated++;
        }

        if ($updated === 0) {
            return back()->withErrors([
                'booking_ids' => 'Ingen valgte bookinger kunne markeres som faktureret.',
            ]);
        }

        $skipped = $bookings->count() - $updated;
        $message = $updated.' booking(er) markeret som faktureret.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' blev sprunget over pga. status.';
        }

        return back()->with('status', $message);
    }

    public function bulkMarkPaid(Request $request): RedirectResponse
    {
        $selectedBookingIds = $this->selectedBookingIds($request);

        $bookings = Booking::query()
            ->whereIn('id', $selectedBookingIds)
            ->with(['timesheets:id,booking_id,status'])
            ->get();

        $updated = 0;

        foreach ($bookings as $booking) {
            if (! $booking->canBePaid() || $booking->timesheets->contains(fn (Timesheet $timesheet): bool => $timesheet->status !== 'approved')) {
                continue;
            }

            $booking->update([
                'is_invoiced' => true,
                'is_paid' => true,
            ]);
            $updated++;
        }

        if ($updated === 0) {
            return back()->withErrors([
                'booking_ids' => 'Ingen valgte bookinger kunne markeres som løn udbetalt.',
            ]);
        }

        $skipped = $bookings->count() - $updated;
        $message = $updated.' booking(er) markeret som løn udbetalt.';

        if ($skipped > 0) {
            $message .= ' '.$skipped.' blev sprunget over pga. status.';
        }

        return back()->with('status', $message);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildFinanceBookings(array $filters): Collection
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
            ->tap(fn (Builder $query) => $this->applyFinanceFilters($query, $filters))
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
            ->limit(500)
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
                            'timesheet_id' => $timesheet?->id,
                            'employee' => $assignment->employee ? [
                                'id' => $assignment->employee->id,
                                'name' => $assignment->employee->name,
                                'email' => $assignment->employee->email,
                            ] : null,
                            'hours_worked' => round($hoursWorked, 2),
                            'wage_total' => round($wageTotal, 2),
                            'price_total' => round($priceTotal, 2),
                            'margin_total' => round($priceTotal - $wageTotal, 2),
                            'timesheet_status' => $timesheet?->status,
                            'can_approve_timesheet' => $timesheet?->status === 'submitted' || $timesheet?->status === 'draft',
                            'can_reopen_timesheet' => $timesheet?->status === 'approved',
                        ];
                    })
                    ->values();

                $wageTotal = round((float) $lines->sum('wage_total'), 2);
                $priceTotal = round((float) $lines->sum('price_total'), 2);
                $hasBlockingTimesheet = $lines->contains(function (array $line): bool {
                    if (! $line['timesheet_status']) {
                        return false;
                    }

                    return $line['timesheet_status'] !== 'approved';
                });

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
                    'can_mark_paid' => $booking->canBePaid() && ! $hasBlockingTimesheet,
                    'can_unmark_paid' => $booking->canPaymentBeRemoved(),
                    'pay_block_reason' => $hasBlockingTimesheet ? 'Alle timesheets skal være godkendt før løn kan markeres udbetalt.' : null,
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

    private function applyFinanceFilters(Builder $query, array $filters): void
    {
        if ($filters['stage'] === 'invoicing') {
            $query->where('is_invoiced', false);
        }

        if ($filters['stage'] === 'payroll') {
            $query->where('is_invoiced', true)
                ->where('is_paid', false);
        }

        if ($filters['from_date']) {
            $query->whereDate('ends_at', '>=', $filters['from_date']);
        }

        if ($filters['to_date']) {
            $query->whereDate('ends_at', '<=', $filters['to_date']);
        }
    }

    /**
     * @return array{stage: string, from_date: ?string, to_date: ?string}
     */
    private function normalizeFilters(Request $request, string $defaultStage = 'invoicing'): array
    {
        $stage = $request->string('stage')->toString();

        if (! in_array($stage, ['all', 'invoicing', 'payroll'], true)) {
            $stage = $defaultStage;
        }

        $fromDate = $request->string('from_date')->toString();
        $toDate = $request->string('to_date')->toString();

        return [
            'stage' => $stage,
            'from_date' => $fromDate !== '' ? $fromDate : null,
            'to_date' => $toDate !== '' ? $toDate : null,
        ];
    }

    /**
     * @return array<int>
     */
    private function selectedBookingIds(Request $request): array
    {
        $validated = $request->validate([
            'booking_ids' => ['required', 'array', 'min:1'],
            'booking_ids.*' => ['integer', 'min:1'],
        ]);

        return array_values(array_unique(array_map('intval', $validated['booking_ids'])));
    }

    private function streamCsv(string $fileName, array $headersRow, callable $writer): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        return response()->streamDownload(function () use ($headersRow, $writer): void {
            $handle = fopen('php://output', 'wb');

            if (! $handle) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headersRow, ';');
            $writer($handle);
            fclose($handle);
        }, $fileName, $headers);
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

        $booking->loadMissing(['timesheets:id,booking_id,status']);

        if ($booking->timesheets->contains(fn (Timesheet $timesheet): bool => $timesheet->status !== 'approved')) {
            return back()->withErrors([
                'booking' => 'Alle timesheets skal være godkendt før løn kan markeres som udbetalt.',
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

    public function approveTimesheet(Timesheet $timesheet): RedirectResponse
    {
        if ($timesheet->status === 'approved') {
            return back()->with('status', 'Timesheet er allerede godkendt.');
        }

        $timesheet->update(['status' => 'approved']);

        return back()->with('status', 'Timesheet godkendt.');
    }

    public function reopenTimesheet(Timesheet $timesheet): RedirectResponse
    {
        if ($timesheet->status !== 'approved') {
            return back()->withErrors([
                'timesheet' => 'Kun godkendte timesheets kan genåbnes.',
            ]);
        }

        $timesheet->update(['status' => 'submitted']);

        return back()->with('status', 'Timesheet genåbnet til behandling.');
    }
}
