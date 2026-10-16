<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'vat_enabled_at_issue')) {
            Schema::table('invoices', static function (Blueprint $table): void {
                $table->boolean('vat_enabled_at_issue')->nullable()->after('tax_rsd');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'vat_enabled_at_issue')
            && DB::table('invoices')->whereNotNull('vat_enabled_at_issue')->exists()) {
            throw new RuntimeException('Cannot rollback VAT snapshot migration while invoice VAT snapshots contain data.');
        }

        if (Schema::hasColumn('invoices', 'vat_enabled_at_issue')) {
            Schema::table('invoices', static function (Blueprint $table): void {
                $table->dropColumn('vat_enabled_at_issue');
            });
        }
    }
};
