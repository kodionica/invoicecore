<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table( 'invoice_settings', static function ( Blueprint $table ) {
            $table->string( 'company_state' );
            $table->string( 'bank_account' );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table( 'invoice_settings', static function ( Blueprint $table ) {
            $table->dropColumn( 'company_state' );
            $table->dropColumn( 'bank_account' );
        } );
    }
};
