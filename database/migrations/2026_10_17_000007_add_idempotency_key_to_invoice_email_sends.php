<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_email_sends', static function (Blueprint $table): void {
            $table->string('idempotency_key', 100)->nullable()->after('invoice_pdf_version_id');
            $table->unique('idempotency_key', 'invoice_email_sends_idempotency_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_email_sends', static function (Blueprint $table): void {
            $table->dropUnique('invoice_email_sends_idempotency_key_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
