<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'invoices_company_invoice_unique';

    private const LEGACY_INDEX = 'invoices_company_invoice_idx';

    public function up(): void
    {
        if ($this->hasDuplicateInvoiceNumbers()) {
            throw new RuntimeException(
                'Cannot add invoice number uniqueness: duplicate invoice numbers exist within a company. Resolve them manually before retrying the migration.'
            );
        }

        if (! $this->hasIndex(self::UNIQUE_INDEX)) {
            Schema::table('invoices', static function (Blueprint $table): void {
                $table->unique(
                    ['company_id', 'invoice_number'],
                    self::UNIQUE_INDEX,
                );
            });
        }

        if ($this->hasIndex(self::LEGACY_INDEX)) {
            Schema::table('invoices', static function (Blueprint $table): void {
                $table->dropIndex(self::LEGACY_INDEX);
            });
        }
    }

    public function down(): void
    {
        if ($this->hasDuplicateInvoiceNumbers()) {
            throw new RuntimeException(
                'Cannot remove invoice number uniqueness while duplicate invoice numbers exist within a company.'
            );
        }

        if ($this->hasIndex(self::UNIQUE_INDEX)) {
            Schema::table('invoices', static function (Blueprint $table): void {
                $table->dropUnique(self::UNIQUE_INDEX);
            });
        }

        if (! $this->hasIndex(self::LEGACY_INDEX)) {
            Schema::table('invoices', static function (Blueprint $table): void {
                $table->index(
                    ['company_id', 'invoice_number'],
                    self::LEGACY_INDEX,
                );
            });
        }
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('invoices'))
            ->contains(static fn (array $index): bool => ($index['name'] ?? null) === $name);
    }

    private function hasDuplicateInvoiceNumbers(): bool
    {
        return DB::table('invoices')
            ->select('company_id')
            ->selectRaw('TRIM(invoice_number) AS normalized_invoice_number')
            ->groupBy('company_id')
            ->groupByRaw('TRIM(invoice_number)')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }
};
