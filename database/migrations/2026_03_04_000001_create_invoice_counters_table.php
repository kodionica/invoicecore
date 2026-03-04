<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create( 'invoice_counters', static function ( Blueprint $table ) {
            $table->id();
            $table->foreignId( 'company_id' )->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger( 'year' );
            $table->unsignedTinyInteger( 'month' );
            $table->unsignedInteger( 'next_number' )->default( 1 );
            $table->timestamps();

            $table->unique( [ 'company_id', 'year', 'month' ] );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists( 'invoice_counters' );
    }
};
