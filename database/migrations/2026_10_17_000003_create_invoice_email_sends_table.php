<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_email_sends', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_pdf_version_id')->nullable()->constrained('invoice_pdf_versions')->nullOnDelete();
            $table->string('recipient');
            $table->string('subject');
            $table->text('message')->nullable();
            $table->string('status');
            $table->text('failure_message')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'invoice_id', 'created_at']);
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_email_sends') && DB::table('invoice_email_sends')->exists()) {
            throw new RuntimeException('Cannot rollback invoice email history while sending records exist.');
        }

        Schema::dropIfExists('invoice_email_sends');
    }
};
