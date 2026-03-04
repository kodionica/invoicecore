<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('invoices', 'currency')) {
            Schema::table('invoices', static function (Blueprint $table) {
                $table->string('currency')->default('RSD');
            });
        }
    }

    public function down(): void {
        if (Schema::hasColumn('invoices', 'currency')) {
            Schema::table('invoices', static function (Blueprint $table) {
                $table->dropColumn('currency');
            });
        }
    }
};
