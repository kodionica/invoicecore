<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create( 'clients', static function ( Blueprint $table ) {
            $table->id();
            $table->foreignId( 'company_id' )->constrained()->cascadeOnDelete();
            $table->string( 'name' );
            $table->string( 'tax_id' )->nullable();
            $table->string( 'registration_number' )->nullable();
            $table->string( 'address' )->nullable();
            $table->string( 'city' )->nullable();
            $table->string( 'country' )->nullable();
            $table->string( 'email' )->nullable();
            $table->string( 'phone' )->nullable();
            $table->timestamps();

            $table->unique( [ 'company_id', 'tax_id', 'registration_number' ] );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists( 'clients' );
    }
};
