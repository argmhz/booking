<?php

namespace App\Services;

use App\Models\Booking;
use Carbon\Carbon;

class BookingLifecycleService
{
    public function syncExecutedBookings(): int
    {
        $now = Carbon::now();

        return Booking::query()
            ->whereNotNull('approved_at')
            ->whereNull('executed_at')
            ->where('ends_at', '<', $now)
            ->update([
                'executed_at' => $now,
                'status' => 'completed',
                'updated_at' => $now,
            ]);
    }
}

