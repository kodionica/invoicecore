<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('iban')->nullable();
            $table->string('swift_bic')->nullable();
            $table->char('currency', 3);
            $table->string('account_type', 20)->default('domestic');
            $table->text('payment_instructions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'currency']);
            $table->index(
                ['company_id', 'currency', 'is_active', 'is_default'],
                'cba_currency_default_idx'
            );
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('bank_account_id')->nullable()->after('client_id')->nullOnDelete();
            $table->string('bank_account_bank_name')->nullable()->after('bank_account_id');
            $table->string('bank_account_number')->nullable()->after('bank_account_bank_name');
            $table->string('bank_account_iban')->nullable()->after('bank_account_number');
            $table->string('bank_account_swift_bic')->nullable()->after('bank_account_iban');
            $table->char('bank_account_currency', 3)->nullable()->after('bank_account_swift_bic');
            $table->text('bank_account_payment_instructions')->nullable()->after('bank_account_currency');

            $table->index('bank_account_id');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('company_bank_accounts') && DB::table('company_bank_accounts')->exists()) {
            throw new RuntimeException('Ne mogu da vratim migraciju: bankovni računi sadrže podatke.');
        }

        if (Schema::hasColumn('invoices', 'bank_account_id') && DB::table('invoices')->whereNotNull('bank_account_id')->exists()) {
            throw new RuntimeException('Ne mogu da vratim migraciju: fakture imaju sačuvan izbor bankovnog računa.');
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['bank_account_id']);
            $table->dropIndex('invoices_bank_account_id_index');
            $table->dropColumn([
                'bank_account_id',
                'bank_account_bank_name',
                'bank_account_number',
                'bank_account_iban',
                'bank_account_swift_bic',
                'bank_account_currency',
                'bank_account_payment_instructions',
            ]);
        });

        Schema::dropIfExists('company_bank_accounts');
    }
};
