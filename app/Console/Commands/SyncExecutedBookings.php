<?php

namespace App\Console\Commands;

use App\Services\BookingLifecycleService;
use Illuminate\Console\Command;

class SyncExecutedBookings extends Command
{
    protected $signature = 'bookings:sync-executed';

    protected $description = 'Markerer godkendte bookinger som eksekverede, når sluttid er passeret';

    public function handle(BookingLifecycleService $bookingLifecycleService): int
    {
        $updated = $bookingLifecycleService->syncExecutedBookings();

        $this->info("Synkroniseret bookinger: {$updated}");

        return self::SUCCESS;
    }
}
