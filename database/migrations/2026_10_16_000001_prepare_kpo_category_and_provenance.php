<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpo_entries', static function (Blueprint $table): void {
            if (! Schema::hasColumn('kpo_entries', 'income_category')) {
                $table->string('income_category')->nullable()->after('income_type');
            }
            if (! Schema::hasColumn('kpo_entries', 'origin')) {
                $table->string('origin')->nullable()->after('source_type');
            }
            if (! Schema::hasColumn('kpo_entries', 'manually_edited_at')) {
                $table->timestamp('manually_edited_at')->nullable()->after('updated_at');
            }
            if (! Schema::hasColumn('kpo_entries', 'manually_edited_by')) {
                $table->foreignId('manually_edited_by')
                    ->nullable()
                    ->after('manually_edited_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        if (Schema::hasColumn('kpo_entries', 'manually_edited_by') && ! $this->hasForeignKey('kpo_entries_manually_edited_by_foreign')) {
            Schema::table('kpo_entries', static function (Blueprint $table): void {
                $table->foreign('manually_edited_by', 'kpo_entries_manually_edited_by_foreign')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        // The composite index cannot support the source_invoice_id foreign key
        // because source_invoice_id is not its leftmost column.
        if (! $this->hasIndex('kpo_entries_source_invoice_id_index')) {
            Schema::table('kpo_entries', static function (Blueprint $table): void {
                $table->index('source_invoice_id', 'kpo_entries_source_invoice_id_index');
            });
        }

        if (! $this->hasIndex('kpo_entries_company_source_category_unique')) {
            Schema::table('kpo_entries', static function (Blueprint $table): void {
                $table->unique(
                    ['company_id', 'source_invoice_id', 'income_category'],
                    'kpo_entries_company_source_category_unique'
                );
            });
        }

        // Drop the old constraint only after both the foreign-key support index
        // and the new category uniqueness constraint are in place.
        if ($this->hasIndex('kpo_entries_source_invoice_id_unique')) {
            Schema::table('kpo_entries', static function (Blueprint $table): void {
                $table->dropUnique('kpo_entries_source_invoice_id_unique');
            });
        }
    }

    private function hasIndex(string $name): bool
    {
        return collect(Schema::getIndexes('kpo_entries'))
            ->contains(static fn (array $index): bool => ($index['name'] ?? null) === $name);
    }

    private function hasForeignKey(string $name): bool
    {
        return collect(Schema::getForeignKeys('kpo_entries'))
            ->contains(static fn (array $foreignKey): bool => ($foreignKey['name'] ?? null) === $name);
    }

    public function down(): void
    {
        $duplicateSourceInvoices = DB::table('kpo_entries')
            ->whereNotNull('source_invoice_id')
            ->select('source_invoice_id')
            ->groupBy('source_invoice_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateSourceInvoices) {
            throw new RuntimeException(
                'Cannot rollback KPO category migration while multiple entries reference the same invoice.'
            );
        }

        if (! $this->hasIndex('kpo_entries_source_invoice_id_unique')) {
            Schema::table('kpo_entries', static function (Blueprint $table): void {
                $table->unique('source_invoice_id', 'kpo_entries_source_invoice_id_unique');
            });
        }

        if ($this->hasIndex('kpo_entries_company_source_category_unique')) {
            Schema::table('kpo_entries', static function (Blueprint $table): void {
                $table->dropUnique('kpo_entries_company_source_category_unique');
            });
        }

        if ($this->hasIndex('kpo_entries_source_invoice_id_index')) {
            Schema::table('kpo_entries', static function (Blueprint $table): void {
                $table->dropIndex('kpo_entries_source_invoice_id_index');
            });
        }

        if ($this->hasForeignKey('kpo_entries_manually_edited_by_foreign')) {
            Schema::table('kpo_entries', static function (Blueprint $table): void {
                $table->dropForeign('kpo_entries_manually_edited_by_foreign');
            });
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('kpo_entries', 'income_category') ? 'income_category' : null,
            Schema::hasColumn('kpo_entries', 'origin') ? 'origin' : null,
            Schema::hasColumn('kpo_entries', 'manually_edited_at') ? 'manually_edited_at' : null,
            Schema::hasColumn('kpo_entries', 'manually_edited_by') ? 'manually_edited_by' : null,
        ]));

        if ($columns !== []) {
            Schema::table('kpo_entries', static function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
