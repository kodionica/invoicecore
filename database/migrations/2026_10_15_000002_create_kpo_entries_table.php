<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpo_entries', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('entry_number');
            $table->date('entry_date');
            $table->string('source_type')->default('manual');
            $table->string('income_type')->nullable();
            $table->string('currency', 3)->default('RSD');
            $table->decimal('amount_original', 14, 2);
            $table->decimal('amount_rsd', 14, 2);
            $table->decimal('adjustment_rsd', 14, 2)->default(0);
            $table->decimal('total_rsd', 14, 2);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'year', 'entry_number']);
            $table->unique('source_invoice_id');
            $table->index(['company_id', 'entry_date']);
            $table->index(['company_id', 'source_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpo_entries');
    }
};
