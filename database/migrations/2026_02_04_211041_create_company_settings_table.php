<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create( 'company_settings', static function ( Blueprint $table ) {
            $table->id();
            $table->foreignId( 'company_id' )->constrained()->cascadeOnDelete();
            $table->string( 'invoice_prefix' )->default( 'INV' );
            $table->unsignedBigInteger( 'next_invoice_number' )->default( 1 );
            $table->string( 'address' )->nullable();
            $table->string( 'city' )->nullable();
            $table->string( 'country' )->nullable();
            $table->string( 'email' )->nullable();
            $table->string( 'phone' )->nullable();
            $table->string( 'bank_account' )->nullable();
            $table->string( 'iban' )->nullable();
            $table->string( 'swift' )->nullable();
            $table->string( 'logo_path' )->nullable();
            $table->string( 'default_currency' )->default( 'RSD' );
            $table->boolean( 'vat_enabled' )->default( false );
            $table->integer( 'default_due_days' )->default( 15 );
            $table->text( 'footer_note' )->nullable();
            $table->timestamps();
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists( 'company_settings' );
    }
};
