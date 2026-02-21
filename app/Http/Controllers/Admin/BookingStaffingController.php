<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingWaitlist;
use App\Services\BookingStaffingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingStaffingController extends Controller
{
    public function __construct(private readonly BookingStaffingService $staffingService)
    {
    }

    public function cancelAssignment(Booking $booking, BookingAssignment $assignment): RedirectResponse
    {
        if ($response = $this->ensureBookingIsMutable($booking)) {
            return $response;
        }

        if ($assignment->booking_id !== $booking->id) {
            abort(404);
        }

        $this->staffingService->cancelAssignment($assignment);

        return back()->with('status', 'Tildeling annulleret.');
    }

    public function promoteWaitlistEntry(Booking $booking, BookingWaitlist $waitlistEntry): RedirectResponse
    {
        if ($response = $this->ensureBookingIsMutable($booking)) {
            return $response;
        }

        if ($waitlistEntry->booking_id !== $booking->id) {
            abort(404);
        }

        if (! $booking->approved_at) {
            return back()->withErrors([
                'booking' => 'Booking skal godkendes, før venteliste kan promoveres.',
            ]);
        }

        $promoted = $this->staffingService->promoteWaitlistEntry($waitlistEntry);

        if (! $promoted) {
            return back()->withErrors([
                'waitlist' => 'Kan ikke promovere ventelisteplads uden ledig kapacitet.',
            ]);
        }

        return back()->with('status', 'Ventelisteplads promoveret til tildeling.');
    }

    public function updateAssignmentRates(Request $request, Booking $booking, BookingAssignment $assignment): RedirectResponse
    {
        if ($response = $this->ensureBookingIsMutable($booking)) {
            return $response;
        }

        if ($assignment->booking_id !== $booking->id) {
            abort(404);
        }

        $validated = $request->validate([
            'worker_rate' => ['nullable', 'numeric', 'min:0'],
            'customer_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->staffingService->updateAssignmentRates(
            $assignment,
            isset($validated['worker_rate']) ? (float) $validated['worker_rate'] : null,
            isset($validated['customer_rate']) ? (float) $validated['customer_rate'] : null,
        );

        return back()->with('status', 'Satser opdateret for medarbejderen.');
    }

    private function ensureBookingIsMutable(Booking $booking): ?RedirectResponse
    {
        if (! $booking->executed_at) {
            return null;
        }

        return back()->withErrors([
            'booking' => 'Bookingen er låst, fordi den er eksekveret.',
        ]);
    }
}
