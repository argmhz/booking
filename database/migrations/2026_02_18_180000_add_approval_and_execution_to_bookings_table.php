<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('approved_at')->nullable()->after('closed_by');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable()->after('approved_by');
            $table->foreignId('executed_by')->nullable()->after('executed_at')->constrained('users')->nullOnDelete();

            $table->index(['approved_at', 'starts_at']);
            $table->index(['executed_at', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['approved_at', 'starts_at']);
            $table->dropIndex(['executed_at', 'starts_at']);

            $table->dropConstrainedForeignId('executed_by');
            $table->dropColumn('executed_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
    }
};
