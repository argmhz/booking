<?php

namespace App\Policies;

use App\Models\BookingRequest;
use App\Models\User;

class BookingRequestPolicy
{
    public function respond(User $user, BookingRequest $bookingRequest): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('employee') && $bookingRequest->employee_user_id === $user->id;
    }
}
