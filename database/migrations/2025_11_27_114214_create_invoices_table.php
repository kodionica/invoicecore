<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create( 'invoices', static function ( Blueprint $table ) {
            $table->id();
            $table->foreignId( 'user_id' )->constrained()->onDelete( 'cascade' );
            $table->foreignId( 'client_id' )->constrained()->onDelete( 'cascade' );

            $table->string( 'invoice_number' );
            $table->date( 'invoice_date' );
            $table->date( 'due_date' )->nullable();

            $table->string( 'currency' )->default( 'EUR' );
            $table->decimal( 'total_amount', 10, 2 )->default( 0 );

            $table->string( 'pdf_path' )->nullable();
            $table->timestamps();
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists( 'invoices' );
    }
};
