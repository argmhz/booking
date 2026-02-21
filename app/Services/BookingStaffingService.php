<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAssignment;
use App\Models\BookingRequest;
use App\Models\Timesheet;
use App\Models\BookingWaitlist;
use App\Models\User;
use App\Notifications\BookingRequestNotification;
use App\Notifications\BookingWaitlistNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class BookingStaffingService
{
    /**
     * @param  Collection<int, int>|array<int, int>  $employeeUserIds
     */
    public function requestEmployees(Booking $booking, Collection|array $employeeUserIds): void
    {
        $ids = collect($employeeUserIds)->unique()->values();
        $recipientIds = [];

        DB::transaction(function () use ($booking, $ids, &$recipientIds): void {
            foreach ($ids as $employeeUserId) {
                BookingRequest::query()->updateOrCreate(
                    [
                        'booking_id' => $booking->id,
                        'employee_user_id' => $employeeUserId,
                    ],
                    [
                        'status' => 'pending',
                        'responded_at' => null,
                    ],
                );

                $recipientIds[] = (int) $employeeUserId;
            }

            $this->syncBookingStatus($booking->fresh());
        });

        if ($recipientIds === []) {
            return;
        }

        $bookingForNotification = $booking->fresh();

        if (! $bookingForNotification) {
            return;
        }

        $bookingForNotification->loadMissing('company:id,name');

        $recipients = User::query()
            ->whereIn('id', array_values(array_unique($recipientIds)))
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new BookingRequestNotification($bookingForNotification));
        }
    }

    public function addEmployeeToBooking(Booking $booking, int $employeeUserId): string
    {
        return DB::transaction(function () use ($booking, $employeeUserId): string {
            $freshBooking = $booking->fresh();

            if (! $freshBooking) {
                return 'error';
            }

            $hasAssignedRecord = BookingAssignment::query()
                ->where('booking_id', $freshBooking->id)
                ->where('employee_user_id', $employeeUserId)
                ->where('status', 'assigned')
                ->exists();

            if ($hasAssignedRecord) {
                return 'already_assigned';
            }

            $this->assignOrWaitlist($freshBooking, $employeeUserId);
            $this->syncBookingStatus($freshBooking->fresh());

            $isAssigned = BookingAssignment::query()
                ->where('booking_id', $freshBooking->id)
                ->where('employee_user_id', $employeeUserId)
                ->where('status', 'assigned')
                ->exists();

            return $isAssigned ? 'assigned' : 'waitlisted';
        });
    }

    public function respondToRequest(BookingRequest $bookingRequest, string $response): void
    {
        DB::transaction(function () use ($bookingRequest, $response): void {
            $request = $bookingRequest->fresh();

            if (! $request || $request->status !== 'pending') {
                return;
            }

            $request->update([
                'status' => $response,
                'responded_at' => Carbon::now(),
            ]);

            if ($response === 'accepted') {
                $this->assignOrWaitlist($request->booking, $request->employee_user_id);
            }

            if ($response === 'declined') {
                $this->leaveWaitlistByEmployee($request->booking, $request->employee_user_id);
            }

            $this->syncBookingStatus($request->booking->fresh());
        });
    }

    public function cancelAssignment(BookingAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            $fresh = $assignment->fresh();

            if (! $fresh || $fresh->status !== 'assigned') {
                return;
            }

            $fresh->update([
                'status' => 'cancelled',
                'cancelled_at' => Carbon::now(),
            ]);

            $this->promoteFromWaitlist($fresh->booking->fresh());
            $this->syncBookingStatus($fresh->booking->fresh());
        });
    }

    public function updateAssignmentRates(BookingAssignment $assignment, ?float $workerRate, ?float $customerRate): void
    {
        $assignment->update([
            'worker_rate' => $workerRate,
            'customer_rate' => $customerRate,
        ]);

        $this->syncTimesheetForAssignment($assignment->fresh());
    }

    public function promoteWaitlistEntry(BookingWaitlist $entry): bool
    {
        return DB::transaction(function () use ($entry): bool {
            $fresh = $entry->fresh();

            if (! $fresh || $fresh->left_at !== null) {
                return false;
            }

            $booking = $fresh->booking->fresh();

            if (! $booking || ! $this->hasOpenSlot($booking)) {
                return false;
            }

            $fresh->update(['left_at' => Carbon::now()]);

            $rates = $this->getEmployeeDefaultRates($fresh->employee_user_id);

            BookingAssignment::query()->updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'employee_user_id' => $fresh->employee_user_id,
                ],
                [
                    'status' => 'assigned',
                    'assigned_at' => Carbon::now(),
                    'cancelled_at' => null,
                    'worker_rate' => $rates['worker_rate'],
                    'customer_rate' => $rates['customer_rate'],
                ],
            );

            $this->reindexWaitlist($booking);
            $this->syncBookingStatus($booking->fresh());

            return true;
        });
    }

    public function leaveWaitlist(BookingWaitlist $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $fresh = $entry->fresh();

            if (! $fresh || $fresh->left_at !== null) {
                return;
            }

            $fresh->update([
                'left_at' => Carbon::now(),
            ]);

            $this->reindexWaitlist($fresh->booking);
        });
    }

    private function assignOrWaitlist(Booking $booking, int $employeeUserId): void
    {
        if ($this->hasOpenSlot($booking)) {
            $rates = $this->getEmployeeDefaultRates($employeeUserId);

            $assignment = BookingAssignment::query()->updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'employee_user_id' => $employeeUserId,
                ],
                [
                    'status' => 'assigned',
                    'assigned_at' => Carbon::now(),
                    'cancelled_at' => null,
                    'worker_rate' => $rates['worker_rate'],
                    'customer_rate' => $rates['customer_rate'],
                ],
            );
            $this->syncTimesheetForAssignment($assignment);

            $this->leaveWaitlistByEmployee($booking, $employeeUserId);

            return;
        }

        $this->addToWaitlist($booking, $employeeUserId);
    }

    private function addToWaitlist(Booking $booking, int $employeeUserId): void
    {
        $activeEntry = BookingWaitlist::query()
            ->where('booking_id', $booking->id)
            ->where('employee_user_id', $employeeUserId)
            ->whereNull('left_at')
            ->first();

        if ($activeEntry) {
            return;
        }

        $nextPosition = (int) BookingWaitlist::query()
            ->where('booking_id', $booking->id)
            ->whereNull('left_at')
            ->max('position') + 1;

        BookingWaitlist::query()->updateOrCreate(
            [
                'booking_id' => $booking->id,
                'employee_user_id' => $employeeUserId,
            ],
            [
                'position' => $nextPosition,
                'joined_at' => Carbon::now(),
                'left_at' => null,
            ],
        );

        $booking->loadMissing('company:id,name');
        $employee = User::query()->find($employeeUserId);

        if ($employee) {
            $employee->notify(new BookingWaitlistNotification($booking, $nextPosition));
        }
    }

    private function leaveWaitlistByEmployee(Booking $booking, int $employeeUserId): void
    {
        BookingWaitlist::query()
            ->where('booking_id', $booking->id)
            ->where('employee_user_id', $employeeUserId)
            ->whereNull('left_at')
            ->update(['left_at' => Carbon::now()]);

        $this->reindexWaitlist($booking);
    }

    private function promoteFromWaitlist(Booking $booking): void
    {
        while ($this->hasOpenSlot($booking)) {
            $next = BookingWaitlist::query()
                ->where('booking_id', $booking->id)
                ->whereNull('left_at')
                ->orderBy('position')
                ->first();

            if (! $next) {
                break;
            }

            $next->update(['left_at' => Carbon::now()]);

            $rates = $this->getEmployeeDefaultRates($next->employee_user_id);

            $assignment = BookingAssignment::query()->updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'employee_user_id' => $next->employee_user_id,
                ],
                [
                    'status' => 'assigned',
                    'assigned_at' => Carbon::now(),
                    'cancelled_at' => null,
                    'worker_rate' => $rates['worker_rate'],
                    'customer_rate' => $rates['customer_rate'],
                ],
            );
            $this->syncTimesheetForAssignment($assignment);
        }

        $this->reindexWaitlist($booking);
    }

    private function reindexWaitlist(Booking $booking): void
    {
        BookingWaitlist::query()
            ->where('booking_id', $booking->id)
            ->whereNull('left_at')
            ->orderBy('position')
            ->get()
            ->values()
            ->each(function (BookingWaitlist $entry, int $index): void {
                $newPosition = $index + 1;

                if ($entry->position !== $newPosition) {
                    $entry->update(['position' => $newPosition]);
                }
            });
    }

    private function hasOpenSlot(Booking $booking): bool
    {
        $assignedCount = BookingAssignment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'assigned')
            ->count();

        return $assignedCount < $booking->required_workers;
    }

    private function syncBookingStatus(Booking $booking): void
    {
        if (in_array($booking->status, ['cancelled', 'completed', 'in_progress'], true)) {
            return;
        }

        $assignedCount = BookingAssignment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'assigned')
            ->count();

        $targetStatus = $assignedCount >= $booking->required_workers ? 'filled' : 'open';

        if ($booking->status !== $targetStatus) {
            $booking->update(['status' => $targetStatus]);
        }
    }

    /**
     * @return array{worker_rate: float|null, customer_rate: float|null}
     */
    private function getEmployeeDefaultRates(int $employeeUserId): array
    {
        $employee = User::query()
            ->with('employeeProfile:id,user_id,hourly_wage,hourly_customer_rate')
            ->find($employeeUserId);

        return [
            'worker_rate' => $employee?->employeeProfile?->hourly_wage !== null
                ? (float) $employee->employeeProfile->hourly_wage
                : null,
            'customer_rate' => $employee?->employeeProfile?->hourly_customer_rate !== null
                ? (float) $employee->employeeProfile->hourly_customer_rate
                : null,
        ];
    }

    private function syncTimesheetForAssignment(?BookingAssignment $assignment): void
    {
        if (! $assignment) {
            return;
        }

        $existing = Timesheet::query()
            ->where('booking_id', $assignment->booking_id)
            ->where('employee_user_id', $assignment->employee_user_id)
            ->first();

        $hours = $existing ? (float) $existing->hours_worked : $this->getBookingPlannedHours($assignment->booking_id);
        $status = $existing?->status ?? 'draft';

        Timesheet::query()->updateOrCreate(
            [
                'booking_id' => $assignment->booking_id,
                'employee_user_id' => $assignment->employee_user_id,
            ],
            [
                'hours_worked' => $hours,
                'hourly_wage' => $assignment->worker_rate,
                'hourly_price' => $assignment->customer_rate,
                'wage_total' => $assignment->worker_rate !== null ? round($hours * (float) $assignment->worker_rate, 2) : null,
                'price_total' => $assignment->customer_rate !== null ? round($hours * (float) $assignment->customer_rate, 2) : null,
                'status' => $status,
            ],
        );
    }

    private function getBookingPlannedHours(int $bookingId): float
    {
        $booking = Booking::query()->select(['id', 'starts_at', 'ends_at'])->find($bookingId);

        if (! $booking?->starts_at || ! $booking?->ends_at) {
            return 0.0;
        }

        return max(0, round($booking->starts_at->diffInMinutes($booking->ends_at, false) / 60, 2));
    }
}
