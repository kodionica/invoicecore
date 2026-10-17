<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kpo_entries', 'deleted_at')) {
            Schema::table('kpo_entries', static function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kpo_entries', 'deleted_at')
            && DB::table('kpo_entries')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('Cannot remove KPO soft-delete support while deleted entries exist.');
        }

        if (Schema::hasColumn('kpo_entries', 'deleted_at')) {
            Schema::table('kpo_entries', static function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
