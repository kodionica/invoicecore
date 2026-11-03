<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', static function (Blueprint $table): void {
            $table->date('service_date')->nullable()->after('issue_date');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Removing service_date would discard invoice history.');
    }
};
