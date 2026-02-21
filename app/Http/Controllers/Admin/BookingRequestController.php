<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingStaffingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingRequestController extends Controller
{
    public function __construct(private readonly BookingStaffingService $staffingService)
    {
    }

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        if ($booking->executed_at) {
            return back()->withErrors([
                'booking' => 'Bookingen er låst, fordi den er eksekveret.',
            ]);
        }

        if (! $booking->approved_at) {
            return back()->withErrors([
                'booking' => 'Booking skal godkendes, før forespørgsler kan sendes.',
            ]);
        }

        $validated = $request->validate([
            'employee_user_ids' => ['nullable', 'array'],
            'employee_user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $ids = collect($validated['employee_user_ids'] ?? [])
            ->unique()
            ->filter()
            ->values();

        if ($booking->assignment_mode === 'specific_employees' && $ids->isEmpty()) {
            return back()->withErrors([
                'employee_user_ids' => 'Vælg mindst én medarbejder for specifik tildeling.',
            ]);
        }

        if ($ids->isEmpty() && $booking->assignment_mode === 'first_come_first_served') {
            $ids = User::query()
                ->role('employee')
                ->where('is_active', true)
                ->pluck('id');
        } else {
            $ids = User::query()
                ->role('employee')
                ->whereIn('id', $ids)
                ->pluck('id');
        }

        $this->staffingService->requestEmployees($booking, $ids);

        return back()->with('status', 'Booking-forespørgsler sendt.');
    }
}
