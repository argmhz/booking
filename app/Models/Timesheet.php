<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timesheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'employee_user_id',
        'hours_worked',
        'hourly_wage',
        'hourly_price',
        'wage_total',
        'price_total',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'hours_worked' => 'decimal:2',
            'hourly_wage' => 'decimal:2',
            'hourly_price' => 'decimal:2',
            'wage_total' => 'decimal:2',
            'price_total' => 'decimal:2',
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
