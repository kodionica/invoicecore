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
            $table->string( 'invoice_prefix' )->default( 'INV' );
            $table->unsignedInteger( 'invoice_start_number' )->default( 1 );
            $table->unsignedInteger( 'invoice_next_number' )->default( 1 );
            $table->string( 'currency' )->default( 'RSD' );
            $table->unsignedTinyInteger( 'default_tax_percent' )->default( 20 );
            $table->boolean( 'vat_enabled' )->default( false );
            $table->unsignedInteger( 'payment_due_days' )->default( 15 );
            $table->text( 'invoice_note' )->nullable();
            $table->json( 'other_settings' )->nullable();
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
