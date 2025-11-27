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
            $table->foreignId( 'user_id' )->constrained()->onDelete( 'cascade' );

            $table->string( 'name' );
            $table->string( 'email' )->nullable();
            $table->string( 'address' )->nullable();
            $table->string( 'country' )->nullable();
            $table->string( 'vat_number' )->nullable(); // For foreign clients (EU)
            $table->string( 'company_number' )->nullable();
            $table->timestamps();
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists( 'clients' );
    }
};
