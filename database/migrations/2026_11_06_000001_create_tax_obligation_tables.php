<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->uuid('request_id');
            $table->char('request_fingerprint', 64);
            $table->string('category', 100);
            $table->string('name', 150);
            $table->date('starts_on');
            $table->string('frequency', 12);
            $table->unsignedSmallInteger('period_count')->nullable();
            $table->unsignedTinyInteger('due_day')->nullable();
            $table->unsignedTinyInteger('due_month_offset')->nullable();
            $table->date('due_on')->nullable();
            $table->boolean('roll_forward');
            $table->unsignedTinyInteger('reminder_days')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'request_id', 'category']);
        });

        Schema::create('tax_schedule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_schedule_id')->constrained()->restrictOnDelete();
            $table->date('effective_on');
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->json('payment_details');
            $table->timestamps();
            $table->unique(['tax_schedule_id', 'effective_on']);
        });

        Schema::create('tax_obligations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_schedule_version_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_on');
            $table->date('remind_on')->nullable();
            $table->unsignedBigInteger('amount_minor')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['tax_schedule_id', 'period_start']);
            $table->index(['company_id', 'period_start']);
            $table->index(['company_id', 'remind_on']);
        });

        Schema::create('tax_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_obligation_id')->constrained()->restrictOnDelete();
            $table->uuid('request_id');
            $table->unsignedBigInteger('amount_minor');
            $table->date('paid_on');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['tax_obligation_id', 'request_id']);
        });

        Schema::create('tax_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_obligation_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('tax_non_working_days', function (Blueprint $table) {
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->primary(['company_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_non_working_days');
        Schema::dropIfExists('tax_attachments');
        Schema::dropIfExists('tax_payments');
        Schema::dropIfExists('tax_obligations');
        Schema::dropIfExists('tax_schedule_versions');
        Schema::dropIfExists('tax_schedules');
    }
};
