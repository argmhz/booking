<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\BookingRequest;
use App\Models\BookingWaitlist;
use App\Services\BookingStaffingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingRequestController extends Controller
{
    public function __construct(private readonly BookingStaffingService $staffingService)
    {
    }

    public function index(Request $request): Response
    {
        $employeeId = $request->user()->id;

        $requests = BookingRequest::query()
            ->where('employee_user_id', $employeeId)
            ->with([
                'booking:id,company_id,title,starts_at,ends_at,required_workers,status',
                'booking.company:id,name',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (BookingRequest $bookingRequest) use ($employeeId): array {
                $waitlistEntry = BookingWaitlist::query()
                    ->where('booking_id', $bookingRequest->booking_id)
                    ->where('employee_user_id', $employeeId)
                    ->whereNull('left_at')
                    ->first();

                return [
                    'id' => $bookingRequest->id,
                    'status' => $bookingRequest->status,
                    'responded_at' => $bookingRequest->responded_at,
                    'booking' => [
                        'id' => $bookingRequest->booking->id,
                        'title' => $bookingRequest->booking->title,
                        'starts_at' => $bookingRequest->booking->starts_at,
                        'ends_at' => $bookingRequest->booking->ends_at,
                        'required_workers' => $bookingRequest->booking->required_workers,
                        'status' => $bookingRequest->booking->status,
                        'company_name' => $bookingRequest->booking->company?->name,
                    ],
                    'waitlist' => $waitlistEntry ? [
                        'id' => $waitlistEntry->id,
                        'position' => $waitlistEntry->position,
                    ] : null,
                ];
            });

        return Inertia::render('Employee/Requests', [
            'requests' => $requests,
        ]);
    }

    public function respond(Request $request, BookingRequest $bookingRequest): RedirectResponse
    {
        $this->authorize('respond', $bookingRequest);

        $validated = $request->validate([
            'response' => ['required', 'in:accepted,declined'],
        ]);

        $this->staffingService->respondToRequest($bookingRequest, $validated['response']);

        return back()->with('status', 'Svar registreret.');
    }

    public function leaveWaitlist(BookingWaitlist $bookingWaitlist): RedirectResponse
    {
        $this->authorize('leave', $bookingWaitlist);

        $this->staffingService->leaveWaitlist($bookingWaitlist);

        return back()->with('status', 'Du har forladt ventelisten.');
    }
}
