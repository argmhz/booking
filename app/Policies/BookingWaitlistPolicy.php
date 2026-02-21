<?php

namespace App\Policies;

use App\Models\BookingWaitlist;
use App\Models\User;

class BookingWaitlistPolicy
{
    public function leave(User $user, BookingWaitlist $bookingWaitlist): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('employee') && $bookingWaitlist->employee_user_id === $user->id;
    }
}
