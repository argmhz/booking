<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceDocumentLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'finance_document_id',
        'booking_id',
        'company_id',
        'employee_user_id',
        'description',
        'hours_worked',
        'wage_total',
        'price_total',
        'margin_total',
    ];

    protected function casts(): array
    {
        return [
            'hours_worked' => 'decimal:2',
            'wage_total' => 'decimal:2',
            'price_total' => 'decimal:2',
            'margin_total' => 'decimal:2',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(FinanceDocument::class, 'finance_document_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }
}

