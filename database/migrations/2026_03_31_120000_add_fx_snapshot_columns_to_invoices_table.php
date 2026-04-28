<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table( 'invoices', static function ( Blueprint $table ) {
            if ( ! Schema::hasColumn( 'invoices', 'fx_rate_to_rsd' ) ) {
                $table->decimal( 'fx_rate_to_rsd', 18, 6 )->nullable()->after( 'currency' );
            }
            if ( ! Schema::hasColumn( 'invoices', 'fx_provider' ) ) {
                $table->string( 'fx_provider' )->nullable()->after( 'fx_rate_to_rsd' );
            }
            if ( ! Schema::hasColumn( 'invoices', 'fx_date' ) ) {
                $table->date( 'fx_date' )->nullable()->after( 'fx_provider' );
            }

            if ( ! Schema::hasColumn( 'invoices', 'subtotal_original' ) ) {
                $table->decimal( 'subtotal_original', 14, 2 )->default( 0 )->after( 'payment_method' );
            }
            if ( ! Schema::hasColumn( 'invoices', 'tax_original' ) ) {
                $table->decimal( 'tax_original', 14, 2 )->default( 0 )->after( 'subtotal_original' );
            }
            if ( ! Schema::hasColumn( 'invoices', 'total_original' ) ) {
                $table->decimal( 'total_original', 14, 2 )->default( 0 )->after( 'tax_original' );
            }
            if ( ! Schema::hasColumn( 'invoices', 'subtotal_rsd' ) ) {
                $table->decimal( 'subtotal_rsd', 14, 2 )->default( 0 )->after( 'total_original' );
            }
            if ( ! Schema::hasColumn( 'invoices', 'tax_rsd' ) ) {
                $table->decimal( 'tax_rsd', 14, 2 )->default( 0 )->after( 'subtotal_rsd' );
            }
            if ( ! Schema::hasColumn( 'invoices', 'total_rsd' ) ) {
                $table->decimal( 'total_rsd', 14, 2 )->default( 0 )->after( 'tax_rsd' );
            }
        } );
    }

    public function down(): void {
        Schema::table( 'invoices', static function ( Blueprint $table ) {
            $dropColumns = array_filter( [
                Schema::hasColumn( 'invoices', 'fx_rate_to_rsd' ) ? 'fx_rate_to_rsd' : null,
                Schema::hasColumn( 'invoices', 'fx_provider' ) ? 'fx_provider' : null,
                Schema::hasColumn( 'invoices', 'fx_date' ) ? 'fx_date' : null,
                Schema::hasColumn( 'invoices', 'subtotal_original' ) ? 'subtotal_original' : null,
                Schema::hasColumn( 'invoices', 'tax_original' ) ? 'tax_original' : null,
                Schema::hasColumn( 'invoices', 'total_original' ) ? 'total_original' : null,
                Schema::hasColumn( 'invoices', 'subtotal_rsd' ) ? 'subtotal_rsd' : null,
                Schema::hasColumn( 'invoices', 'tax_rsd' ) ? 'tax_rsd' : null,
                Schema::hasColumn( 'invoices', 'total_rsd' ) ? 'total_rsd' : null,
            ] );

            if ( $dropColumns !== [] ) {
                $table->dropColumn( $dropColumns );
            }
        } );
    }
};
