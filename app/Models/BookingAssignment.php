<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'employee_user_id',
        'status',
        'assigned_at',
        'cancelled_at',
        'worker_rate',
        'customer_rate',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'worker_rate' => 'decimal:2',
            'customer_rate' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

}
