<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_pdf_versions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('path');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('protected_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unique(['invoice_id', 'version']);
            $table->unique('path');
            $table->index(['company_id', 'invoice_id']);
            $table->index(['invoice_id', 'protected_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_pdf_versions') && DB::table('invoice_pdf_versions')->exists()) {
            throw new RuntimeException('Cannot rollback invoice PDF versions while version history contains data.');
        }

        Schema::dropIfExists('invoice_pdf_versions');
    }
};
