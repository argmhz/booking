<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city');
            $table->string('country', 120)->default('Denmark');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'is_default']);
            $table->index(['company_id', 'city']);
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('company_address_id')
                ->nullable()
                ->after('company_id')
                ->constrained('company_addresses')
                ->nullOnDelete();

            $table->index(['company_id', 'company_address_id']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'company_address_id']);
            $table->dropConstrainedForeignId('company_address_id');
        });

        Schema::dropIfExists('company_addresses');
    }
};
