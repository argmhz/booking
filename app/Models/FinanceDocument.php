<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'status',
        'period_from',
        'period_to',
        'wage_total',
        'price_total',
        'margin_total',
        'finalized_at',
        'created_by',
        'finalized_by',
    ];

    protected function casts(): array
    {
        return [
            'period_from' => 'date',
            'period_to' => 'date',
            'wage_total' => 'decimal:2',
            'price_total' => 'decimal:2',
            'margin_total' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FinanceDocumentLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}

