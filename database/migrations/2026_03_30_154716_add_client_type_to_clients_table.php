<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table( 'clients', static function ( Blueprint $table ) {
            $table->enum( 'client_type', [ 'b2b', 'b2c' ] )->default( 'b2b' );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::table( 'clients', function ( Blueprint $table ) {
            //
        } );
    }
};
