<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_pdf_versions')) {
            return;
        }

        DB::table('invoices')->whereNotNull('pdf_path')->orderBy('id')->chunkById(100, static function ($invoices): void {
            foreach ($invoices as $invoice) {
                if (DB::table('invoice_pdf_versions')->where('invoice_id', $invoice->id)->exists()) {
                    continue;
                }

                DB::table('invoice_pdf_versions')->insert([
                    'company_id' => $invoice->company_id,
                    'invoice_id' => $invoice->id,
                    'version' => 1,
                    'path' => $invoice->pdf_path,
                    'created_at' => $invoice->updated_at ?? now(),
                    // Existing storage does not prove whether the PDF was sent.
                    // Protect it conservatively; sent_at remains unknown.
                    'protected_at' => $invoice->updated_at ?? now(),
                    'sent_at' => null,
                ]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_pdf_versions') && DB::table('invoice_pdf_versions')->exists()) {
            throw new RuntimeException('Cannot rollback the legacy PDF backfill without risking version history.');
        }
    }
};
