<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\FinanceDocument;
use App\Models\FinanceDocumentLine;
use App\Models\Timesheet;
use App\Services\BookingLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceDocumentController extends Controller
{
    public function __construct(private readonly BookingLifecycleService $bookingLifecycleService)
    {
    }

    public function index(): Response
    {
        $documents = FinanceDocument::query()
            ->with([
                'creator:id,name,email',
                'finalizer:id,name,email',
                'lines:id,finance_document_id,booking_id,company_id,employee_user_id,description,hours_worked,wage_total,price_total,margin_total',
                'lines.company:id,name',
                'lines.employee:id,name,email',
            ])
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (FinanceDocument $document): array {
                return [
                    'id' => $document->id,
                    'type' => $document->type,
                    'status' => $document->status,
                    'period_from' => $document->period_from,
                    'period_to' => $document->period_to,
                    'wage_total' => (float) $document->wage_total,
                    'price_total' => (float) $document->price_total,
                    'margin_total' => (float) $document->margin_total,
                    'created_at' => $document->created_at,
                    'finalized_at' => $document->finalized_at,
                    'creator' => $document->creator ? [
                        'id' => $document->creator->id,
                        'name' => $document->creator->name,
                        'email' => $document->creator->email,
                    ] : null,
                    'finalizer' => $document->finalizer ? [
                        'id' => $document->finalizer->id,
                        'name' => $document->finalizer->name,
                        'email' => $document->finalizer->email,
                    ] : null,
                    'lines' => $document->lines->map(fn (FinanceDocumentLine $line): array => [
                        'id' => $line->id,
                        'booking_id' => $line->booking_id,
                        'description' => $line->description,
                        'hours_worked' => (float) $line->hours_worked,
                        'wage_total' => (float) $line->wage_total,
                        'price_total' => (float) $line->price_total,
                        'margin_total' => (float) $line->margin_total,
                        'company' => $line->company ? [
                            'id' => $line->company->id,
                            'name' => $line->company->name,
                        ] : null,
                        'employee' => $line->employee ? [
                            'id' => $line->employee->id,
                            'name' => $line->employee->name,
                            'email' => $line->employee->email,
                        ] : null,
                    ])->values(),
                ];
            })
            ->values();

        return Inertia::render('Admin/Finance/Documents/Index', [
            'documents' => $documents,
        ]);
    }

    public function storeInvoiceDraft(Request $request): RedirectResponse
    {
        $this->bookingLifecycleService->syncExecutedBookings();
        $bookingIds = $this->selectedBookingIds($request);
        $bookingData = $this->buildBookingFinancialData($bookingIds);

        $eligible = $bookingData->filter(fn (array $booking): bool => (bool) $booking['can_invoice'])->values();
        if ($eligible->isEmpty()) {
            return back()->withErrors([
                'booking_ids' => 'Ingen af de valgte bookinger kan bruges til en fakturakladde.',
            ]);
        }

        $document = DB::transaction(function () use ($eligible, $request): FinanceDocument {
            $document = FinanceDocument::query()->create([
                'type' => 'invoice',
                'status' => 'draft',
                'period_from' => $eligible->min('ends_at_date'),
                'period_to' => $eligible->max('ends_at_date'),
                'created_by' => $request->user()?->id,
            ]);

            foreach ($eligible as $booking) {
                $document->lines()->create([
                    'booking_id' => $booking['id'],
                    'company_id' => $booking['company_id'],
                    'employee_user_id' => null,
                    'description' => $booking['title'],
                    'hours_worked' => $booking['hours_total'],
                    'wage_total' => $booking['wage_total'],
                    'price_total' => $booking['price_total'],
                    'margin_total' => $booking['margin_total'],
                ]);
            }

            $this->syncDocumentTotals($document);

            return $document;
        });

        $skipped = $bookingData->count() - $eligible->count();
        $message = "Fakturakladde #{$document->id} oprettet med {$eligible->count()} booking(er).";
        if ($skipped > 0) {
            $message .= " {$skipped} blev sprunget over pga. status.";
        }

        return back()->with('status', $message);
    }

    public function storePayrollDraft(Request $request): RedirectResponse
    {
        $this->bookingLifecycleService->syncExecutedBookings();
        $bookingIds = $this->selectedBookingIds($request);
        $bookingData = $this->buildBookingFinancialData($bookingIds);

        $eligible = $bookingData->filter(fn (array $booking): bool => (bool) $booking['can_payroll'])->values();
        if ($eligible->isEmpty()) {
            return back()->withErrors([
                'booking_ids' => 'Ingen af de valgte bookinger kan bruges til en lønkladde.',
            ]);
        }

        $document = DB::transaction(function () use ($eligible, $request): FinanceDocument {
            $document = FinanceDocument::query()->create([
                'type' => 'payroll',
                'status' => 'draft',
                'period_from' => $eligible->min('ends_at_date'),
                'period_to' => $eligible->max('ends_at_date'),
                'created_by' => $request->user()?->id,
            ]);

            foreach ($eligible as $booking) {
                foreach ($booking['assignment_lines'] as $line) {
                    $document->lines()->create([
                        'booking_id' => $booking['id'],
                        'company_id' => $booking['company_id'],
                        'employee_user_id' => $line['employee_user_id'],
                        'description' => $booking['title'].' - '.$line['employee_name'],
                        'hours_worked' => $line['hours_worked'],
                        'wage_total' => $line['wage_total'],
                        'price_total' => $line['price_total'],
                        'margin_total' => $line['margin_total'],
                    ]);
                }
            }

            $this->syncDocumentTotals($document);

            return $document;
        });

        $skipped = $bookingData->count() - $eligible->count();
        $message = "Lønkladde #{$document->id} oprettet med {$eligible->count()} booking(er).";
        if ($skipped > 0) {
            $message .= " {$skipped} blev sprunget over pga. status/timesheets.";
        }

        return back()->with('status', $message);
    }

    public function finalize(FinanceDocument $document, Request $request): RedirectResponse
    {
        if ($document->status !== 'draft') {
            return back()->withErrors([
                'document' => 'Kun kladder kan finaliseres.',
            ]);
        }

        $bookingIds = $document->lines()->distinct()->pluck('booking_id')->all();
        $bookings = Booking::query()
            ->whereIn('id', $bookingIds)
            ->with(['timesheets:id,booking_id,status'])
            ->get()
            ->keyBy('id');

        $invalidBookingIds = [];

        foreach ($bookingIds as $bookingId) {
            $booking = $bookings->get($bookingId);

            if (! $booking) {
                $invalidBookingIds[] = $bookingId;
                continue;
            }

            if ($document->type === 'invoice' && ! $booking->canBeInvoiced()) {
                $invalidBookingIds[] = $bookingId;
                continue;
            }

            if ($document->type === 'payroll') {
                $hasBlockingTimesheet = $booking->timesheets->contains(
                    fn (Timesheet $timesheet): bool => $timesheet->status !== 'approved'
                );

                if (! $booking->canBePaid() || $hasBlockingTimesheet) {
                    $invalidBookingIds[] = $bookingId;
                }
            }
        }

        if ($invalidBookingIds !== []) {
            return back()->withErrors([
                'document' => 'Kladde kan ikke finaliseres. Ugyldige booking IDs: '.implode(', ', $invalidBookingIds),
            ]);
        }

        DB::transaction(function () use ($document, $bookings, $bookingIds, $request): void {
            foreach ($bookingIds as $bookingId) {
                $booking = $bookings->get($bookingId);
                if (! $booking) {
                    continue;
                }

                if ($document->type === 'invoice') {
                    $booking->update(['is_invoiced' => true]);
                }

                if ($document->type === 'payroll') {
                    $booking->update([
                        'is_invoiced' => true,
                        'is_paid' => true,
                    ]);
                }
            }

            $document->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'finalized_by' => $request->user()?->id,
            ]);
        });

        return back()->with('status', "Kladde #{$document->id} finaliseret.");
    }

    public function exportCsv(FinanceDocument $document): StreamedResponse
    {
        $document->loadMissing([
            'lines:id,finance_document_id,booking_id,company_id,employee_user_id,description,hours_worked,wage_total,price_total,margin_total',
            'lines.company:id,name',
            'lines.employee:id,name,email',
        ]);

        $prefix = $document->type === 'invoice' ? 'fakturakladde' : 'loenkladde';
        $fileName = "{$prefix}-{$document->id}-".now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($document): void {
            $handle = fopen('php://output', 'wb');
            if (! $handle) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Dokument ID',
                'Type',
                'Status',
                'Booking ID',
                'Beskrivelse',
                'Virksomhed',
                'Medarbejder',
                'Timer',
                'Løn total',
                'Pris total',
                'Margin',
            ], ';');

            foreach ($document->lines as $line) {
                fputcsv($handle, [
                    $document->id,
                    $document->type,
                    $document->status,
                    $line->booking_id,
                    $line->description,
                    $line->company?->name ?? '',
                    $line->employee?->name ?? '',
                    $line->hours_worked,
                    $line->wage_total,
                    $line->price_total,
                    $line->margin_total,
                ], ';');
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
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

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildBookingFinancialData(array $bookingIds): Collection
    {
        return Booking::query()
            ->whereIn('id', $bookingIds)
            ->with([
                'assignments' => function ($query): void {
                    $query->select(['id', 'booking_id', 'employee_user_id', 'status', 'worker_rate', 'customer_rate'])
                        ->where('status', 'assigned')
                        ->with('employee:id,name');
                },
                'timesheets' => function ($query): void {
                    $query->select(['id', 'booking_id', 'employee_user_id', 'hours_worked', 'wage_total', 'price_total', 'status']);
                },
            ])
            ->get()
            ->map(function (Booking $booking): array {
                $timesheetsByEmployee = $booking->timesheets->keyBy('employee_user_id');
                $bookingHours = $booking->starts_at && $booking->ends_at
                    ? max(0, round($booking->starts_at->diffInMinutes($booking->ends_at, false) / 60, 2))
                    : 0.0;

                $assignmentLines = $booking->assignments
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
                            'employee_user_id' => $assignment->employee_user_id,
                            'employee_name' => $assignment->employee?->name ?? 'Ukendt',
                            'hours_worked' => round($hoursWorked, 2),
                            'wage_total' => round($wageTotal, 2),
                            'price_total' => round($priceTotal, 2),
                            'margin_total' => round($priceTotal - $wageTotal, 2),
                            'timesheet_status' => $timesheet?->status,
                        ];
                    })
                    ->values();

                $hasBlockingTimesheet = $assignmentLines->contains(function (array $line): bool {
                    if (! $line['timesheet_status']) {
                        return false;
                    }

                    return $line['timesheet_status'] !== 'approved';
                });

                $wageTotal = round((float) $assignmentLines->sum('wage_total'), 2);
                $priceTotal = round((float) $assignmentLines->sum('price_total'), 2);
                $marginTotal = round($priceTotal - $wageTotal, 2);

                return [
                    'id' => $booking->id,
                    'company_id' => $booking->company_id,
                    'title' => $booking->title,
                    'ends_at_date' => $booking->ends_at?->toDateString(),
                    'hours_total' => round((float) $assignmentLines->sum('hours_worked'), 2),
                    'wage_total' => $wageTotal,
                    'price_total' => $priceTotal,
                    'margin_total' => $marginTotal,
                    'assignment_lines' => $assignmentLines,
                    'can_invoice' => $booking->canBeInvoiced(),
                    'can_payroll' => $booking->canBePaid() && ! $hasBlockingTimesheet,
                ];
            })
            ->values();
    }

    private function syncDocumentTotals(FinanceDocument $document): void
    {
        $totals = $document->lines()
            ->selectRaw('COALESCE(SUM(hours_worked), 0) as hours_total, COALESCE(SUM(wage_total), 0) as wage_total, COALESCE(SUM(price_total), 0) as price_total, COALESCE(SUM(margin_total), 0) as margin_total')
            ->first();

        $document->update([
            'wage_total' => round((float) ($totals?->wage_total ?? 0), 2),
            'price_total' => round((float) ($totals?->price_total ?? 0), 2),
            'margin_total' => round((float) ($totals?->margin_total ?? 0), 2),
        ]);
    }
}

