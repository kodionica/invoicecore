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
            $table->unsignedBigInteger( 'company_id' )->primary();
            $table->foreign( 'company_id' )->references( 'id' )->on( 'companies' )->cascadeOnDelete();
            $table->string( 'invoice_prefix' )->default( 'INV' );
            $table->unsignedInteger( 'invoice_start_number' )->default( 1 );
            $table->unsignedInteger( 'invoice_next_number' )->default( 1 );
            $table->string( 'currency' )->default( 'RSD' );
            $table->unsignedTinyInteger( 'default_tax_percent' )->default( 20 );
            $table->boolean( 'vat_enabled' )->default( false );
            $table->unsignedInteger( 'payment_due_days' )->default( 15 );
            $table->text( 'invoice_note' )->nullable();
            $table->json( 'other_settings' )->nullable();
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
