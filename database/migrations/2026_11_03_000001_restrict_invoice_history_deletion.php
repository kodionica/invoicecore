<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->replaceConstraints(true);
    }

    public function down(): void
    {
        $this->replaceConstraints(false);
    }

    private function replaceConstraints(bool $restrict): void
    {
        foreach ([
            'invoices' => ['client_id' => 'clients', 'company_id' => 'companies'],
            'invoice_pdf_versions' => ['company_id' => 'companies'],
            'invoice_email_sends' => ['company_id' => 'companies'],
            'kpo_entries' => ['company_id' => 'companies'],
        ] as $name => $keys) {
            if (in_array(DB::connection()->getDriverName(), ['mariadb', 'mysql'], true)) {
                $actions = [];
                $rule = $restrict ? 'RESTRICT' : 'CASCADE';
                foreach ($keys as $column => $parent) {
                    $constraint = "{$name}_{$column}_foreign";
                    $restrictedConstraint = "{$name}_{$column}_restrict_fk";
                    $old = $restrict ? $constraint : $restrictedConstraint;
                    $new = $restrict ? $restrictedConstraint : $constraint;
                    $actions[] = "DROP FOREIGN KEY `{$old}`";
                    $actions[] = "ADD CONSTRAINT `{$new}` FOREIGN KEY (`{$column}`) REFERENCES `{$parent}` (`id`) ON DELETE {$rule}";
                }
                DB::statement("ALTER TABLE `{$name}` ".implode(', ', $actions));

                continue;
            }

            foreach ($keys as $column => $parent) {
                Schema::table($name, static function (Blueprint $table) use ($column, $parent, $restrict): void {
                    $table->dropForeign([$column]);
                    $table->foreign($column)->references('id')->on($parent)
                        ->onDelete($restrict ? 'restrict' : 'cascade');
                });
            }
        }
    }
};
