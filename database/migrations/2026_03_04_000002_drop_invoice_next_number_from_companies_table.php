<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table( 'companies', static function ( Blueprint $table ) {
            $table->dropColumn( 'invoice_next_number' );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table( 'companies', static function ( Blueprint $table ) {
            $table->unsignedInteger( 'invoice_next_number' )->default( 1 );
        } );
    }
};
