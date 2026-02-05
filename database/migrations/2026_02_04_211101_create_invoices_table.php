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
            $table->foreignId( 'company_id' )->constrained()->cascadeOnDelete();
            $table->foreignId( 'client_id' )->constrained()->cascadeOnDelete();

            $table->string( 'number' );
            $table->date( 'issue_date' );
            $table->date( 'service_date' )->nullable();
            $table->date( 'due_date' );

            $table->string( 'currency' )->default( 'RSD' );
            $table->string( 'payment_method' )->nullable();

            $table->decimal( 'total', 12, 2 )->default( 0 );
            $table->string( 'status' )->default( 'draft' ); // draft, sent, paid
            $table->string( 'pdf_path' )->nullable();

            $table->text( 'note' )->nullable();
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
