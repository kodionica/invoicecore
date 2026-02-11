<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create( 'companies', static function ( Blueprint $table ) {
            $table->id();
            $table->string( 'name' );
            $table->string( 'tax_id' )->nullable()->unique();
            $table->string( 'registration_number' )->nullable()->unique();
            $table->string( 'address' )->nullable();
            $table->string( 'city' )->nullable();
            $table->string( 'country' )->nullable();
            $table->string( 'email' )->nullable();
            $table->string( 'phone' )->nullable();
            $table->string( 'bank_account' )->nullable();
            $table->string( 'iban' )->nullable();
            $table->string( 'swift' )->nullable();
            $table->string( 'logo_path' )->nullable();
            $table->foreignId( 'user_id' )->constrained()->cascadeOnDelete();
            $table->timestamps();
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists( 'companies' );
    }
};
