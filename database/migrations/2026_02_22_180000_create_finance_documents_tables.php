<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('finance_documents', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['invoice', 'payroll']);
            $table->enum('status', ['draft', 'finalized', 'cancelled'])->default('draft');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->decimal('wage_total', 12, 2)->default(0);
            $table->decimal('price_total', 12, 2)->default(0);
            $table->decimal('margin_total', 12, 2)->default(0);
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('finance_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description');
            $table->decimal('hours_worked', 8, 2)->default(0);
            $table->decimal('wage_total', 12, 2)->default(0);
            $table->decimal('price_total', 12, 2)->default(0);
            $table->decimal('margin_total', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['finance_document_id', 'booking_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_document_lines');
        Schema::dropIfExists('finance_documents');
    }
};

