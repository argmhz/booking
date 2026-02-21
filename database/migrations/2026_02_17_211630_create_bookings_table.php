<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('required_workers');
            $table->enum('assignment_mode', ['specific_employees', 'first_come_first_served']);
            $table->boolean('show_employee_names_to_company')->default(false);
            $table->enum('status', ['draft', 'open', 'filled', 'in_progress', 'completed', 'cancelled'])->default('open');
            $table->decimal('hourly_customer_rate', 10, 2)->nullable();
            $table->decimal('hourly_worker_rate', 10, 2)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_invoiced')->default(false);
            $table->boolean('is_paid')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
