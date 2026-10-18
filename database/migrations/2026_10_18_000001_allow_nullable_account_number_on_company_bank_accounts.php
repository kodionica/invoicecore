<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('company_bank_accounts', function (Blueprint $table): void {
            $table->string('account_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('company_bank_accounts')->whereNull('account_number')->exists()) {
            throw new RuntimeException('Ne mogu da vratim account_number na NOT NULL: postoje devizni računi bez broja računa.');
        }

        Schema::table('company_bank_accounts', function (Blueprint $table): void {
            $table->string('account_number')->nullable(false)->change();
        });
    }
};
