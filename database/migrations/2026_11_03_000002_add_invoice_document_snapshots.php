<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->timestamp('issued_at')->nullable();
            $table->json('seller_snapshot')->nullable();
            $table->json('buyer_snapshot')->nullable();
            $table->json('document_settings')->nullable();
            $table->boolean('legacy_protected')->default(false);
        });
        Schema::table('invoice_items', static function (Blueprint $table): void {
            $table->decimal('tax_percent', 7, 4)->nullable();
            $table->unsignedInteger('position')->nullable();
        });
        \Illuminate\Support\Facades\DB::table('invoices')
            ->where(static function ($query): void {
                $query->where('status', '!=', 'draft')
                    ->orWhereExists(static function ($versions): void {
                        $versions->selectRaw('1')->from('invoice_pdf_versions')
                            ->whereColumn('invoice_pdf_versions.invoice_id', 'invoices.id')
                            ->whereNotNull('protected_at');
                    })
                    ->orWhereExists(static function ($emails): void {
                        $emails->selectRaw('1')->from('invoice_email_sends')
                            ->whereColumn('invoice_email_sends.invoice_id', 'invoices.id')
                            ->whereNotNull('accepted_at');
                    });
            })->update(['legacy_protected' => true]);
    }

    public function down(): void
    {
        throw new RuntimeException('Invoice document snapshots cannot be removed without losing historical data.');
    }
};
