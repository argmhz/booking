<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingWaitlist;
use App\Models\Company;
use App\Models\User;
use App\Notifications\BookingApprovedNotification;
use App\Services\BookingStaffingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BookingManagementController extends Controller
{
    public function __construct(private readonly BookingStaffingService $staffingService)
    {
    }

    public function create(Request $request): Response
    {
        $initialDate = $request->query('date');

        return Inertia::render('Bookings/Create', [
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
            'initialDate' => is_string($initialDate) ? $initialDate : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateBooking($request);

        Booking::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status' => 'open',
        ]);

        return redirect()->route('bookings.calendar')->with('status', 'Booking oprettet.');
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        if ($response = $this->ensureBookingIsMutable($booking)) {
            return $response;
        }

        $validated = $this->validateBooking($request);

        $booking->update($validated);

        return back()->with('status', 'Booking opdateret.');
    }

    public function destroy(Booking $booking): RedirectResponse
    {
        if ($response = $this->ensureBookingIsMutable($booking)) {
            return $response;
        }

        $booking->delete();

        return redirect()->route('bookings.calendar')->with('status', 'Booking slettet.');
    }

    public function addEmployee(Request $request, Booking $booking): RedirectResponse
    {
        if ($response = $this->ensureBookingIsMutable($booking)) {
            return $response;
        }

        $validated = $request->validate([
            'employee_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $employeeId = User::query()
            ->role('employee')
            ->where('is_active', true)
            ->where('id', $validated['employee_user_id'])
            ->value('id');

        if (! $employeeId) {
            return back()->withErrors([
                'employee_user_id' => 'Kun aktive medarbejdere kan tilføjes.',
            ]);
        }

        $result = $this->staffingService->addEmployeeToBooking($booking, (int) $employeeId);

        if ($result === 'already_assigned') {
            return back()->withErrors([
                'employee_user_id' => 'Medarbejderen er allerede tildelt.',
            ]);
        }

        return back()->with('status', $result === 'assigned'
            ? 'Medarbejder tilføjet til booking.'
            : 'Medarbejder tilføjet til venteliste.');
    }

    public function removeWaitlistEntry(Booking $booking, BookingWaitlist $waitlistEntry): RedirectResponse
    {
        if ($response = $this->ensureBookingIsMutable($booking)) {
            return $response;
        }

        if ($waitlistEntry->booking_id !== $booking->id) {
            abort(404);
        }

        $this->staffingService->leaveWaitlist($waitlistEntry);

        return back()->with('status', 'Medarbejder fjernet fra ventelisten.');
    }

    public function approve(Booking $booking, Request $request): RedirectResponse
    {
        if ($response = $this->ensureBookingIsMutable($booking)) {
            return $response;
        }

        if (! $booking->canBeApproved()) {
            return back()->withErrors([
                'booking' => 'Bookingen kan ikke godkendes i dens nuværende status.',
            ]);
        }

        $booking->update([
            'approved_at' => Carbon::now(),
            'approved_by' => $request->user()->id,
        ]);

        $booking->loadMissing('company.users', 'assignments.employee');
        $recipients = collect();

        if ($booking->company) {
            $recipients = $recipients->merge($booking->company->users);
        }

        $assignedEmployees = $booking->assignments
            ->where('status', 'assigned')
            ->pluck('employee')
            ->filter();

        $recipients = $recipients
            ->merge($assignedEmployees)
            ->unique('id')
            ->values();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new BookingApprovedNotification($booking, $request->user()));
        }

        return back()->with('status', 'Booking godkendt.');
    }

    public function revokeApproval(Booking $booking): RedirectResponse
    {
        if ($response = $this->ensureBookingIsMutable($booking)) {
            return $response;
        }

        if (! $booking->canApprovalBeRevoked()) {
            return back()->withErrors([
                'booking' => 'Godkendelse kan kun fjernes fra en godkendt booking, som ikke er eksekveret.',
            ]);
        }

        $assignedCount = BookingAssignment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'assigned')
            ->count();

        $booking->update([
            'approved_at' => null,
            'approved_by' => null,
            'executed_at' => null,
            'executed_by' => null,
            'status' => $assignedCount >= $booking->required_workers ? 'filled' : 'open',
        ]);

        return back()->with('status', 'Godkendelse fjernet.');
    }

    private function validateBooking(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'company_address_id' => [
                'nullable',
                'integer',
                Rule::exists('company_addresses', 'id')->where(
                    fn ($query) => $query->where('company_id', $request->input('company_id'))
                ),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'required_workers' => ['required', 'integer', 'min:1', 'max:1000'],
            'assignment_mode' => ['required', 'in:specific_employees,first_come_first_served'],
            'show_employee_names_to_company' => ['required', 'boolean'],
        ]);
    }

    private function ensureBookingIsMutable(Booking $booking): ?RedirectResponse
    {
        if ($booking->canBeEdited()) {
            return null;
        }

        return back()->withErrors([
            'booking' => 'Bookingen er låst, fordi den er eksekveret.',
        ]);
    }
}
