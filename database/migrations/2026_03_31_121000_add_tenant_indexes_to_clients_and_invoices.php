<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table( 'invoices', static function ( Blueprint $table ) {
            $table->index( [ 'company_id', 'invoice_number' ], 'invoices_company_invoice_idx' );
        } );

        Schema::table( 'clients', static function ( Blueprint $table ) {
            $table->index( [ 'company_id', 'name' ], 'clients_company_name_idx' );
        } );
    }

    public function down(): void {
        Schema::table( 'invoices', static function ( Blueprint $table ) {
            $table->dropIndex( 'invoices_company_invoice_idx' );
        } );

        Schema::table( 'clients', static function ( Blueprint $table ) {
            $table->dropIndex( 'clients_company_name_idx' );
        } );
    }
};
