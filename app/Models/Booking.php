<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $appends = [
        'workflow_status',
    ];

    protected $fillable = [
        'company_id',
        'company_address_id',
        'created_by',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'required_workers',
        'assignment_mode',
        'show_employee_names_to_company',
        'status',
        'closed_at',
        'closed_by',
        'approved_at',
        'approved_by',
        'executed_at',
        'executed_by',
        'is_invoiced',
        'is_paid',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'closed_at' => 'datetime',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
            'show_employee_names_to_company' => 'boolean',
            'is_invoiced' => 'boolean',
            'is_paid' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function companyAddress(): BelongsTo
    {
        return $this->belongsTo(CompanyAddress::class, 'company_address_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(BookingRequest::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BookingAssignment::class);
    }

    public function waitlistEntries(): HasMany
    {
        return $this->hasMany(BookingWaitlist::class, 'booking_id');
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class, 'booking_id');
    }

    public function financeDocumentLines(): HasMany
    {
        return $this->hasMany(FinanceDocumentLine::class);
    }

    public function getWorkflowStatusAttribute(): string
    {
        if ($this->is_paid) {
            return 'paid';
        }

        if ($this->is_invoiced) {
            return 'invoiced';
        }

        if ($this->executed_at) {
            return 'executed';
        }

        if ($this->approved_at) {
            return 'approved';
        }

        return 'open';
    }

    public function canBeEdited(): bool
    {
        return ! $this->executed_at;
    }

    public function canBeApproved(): bool
    {
        return ! $this->approved_at && ! $this->executed_at;
    }

    public function canApprovalBeRevoked(): bool
    {
        return (bool) $this->approved_at && ! $this->executed_at;
    }

    public function canBeInvoiced(): bool
    {
        return (bool) $this->executed_at && ! $this->is_invoiced;
    }

    public function canInvoiceBeRemoved(): bool
    {
        return $this->is_invoiced && ! $this->is_paid;
    }

    public function canBePaid(): bool
    {
        return (bool) $this->executed_at && $this->is_invoiced && ! $this->is_paid;
    }

    public function canPaymentBeRemoved(): bool
    {
        return $this->is_paid;
    }
}
