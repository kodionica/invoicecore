<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create( 'invoice_settings', static function ( Blueprint $table ) {
            $table->id();
            $table->foreignId( 'user_id' )->constrained()->onDelete( 'cascade' );

            $table->string( 'company_name' );
            $table->string( 'company_address' );
            $table->string( 'company_email' );
            $table->string( 'company_phone' );

            $table->string( 'pib' );
            $table->string( 'iban' )->nullable();
            $table->string( 'swift' )->nullable();

            $table->string( 'logo_path' )->nullable();

            $table->string( 'default_currency' )->default( 'EUR' );
            $table->integer( 'default_due_days' )->default( 15 );

            $table->text( 'footer_note' )->nullable();
            $table->timestamps();
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists( 'invoice_settings' );
    }
};
