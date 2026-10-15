<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::table( 'settings', static function ( Blueprint $table ) {
            $table->dropUnique( [ 'key' ] );
            $table->foreignId( 'user_id' )
                ->nullable()
                ->after( 'id' )
                ->constrained( 'users' )
                ->cascadeOnDelete();
            $table->unique( [ 'user_id', 'key' ], 'settings_user_id_key_unique' );
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        if ( DB::table( 'settings' )->whereNotNull( 'user_id' )->exists() ) {
            throw new RuntimeException(
                'Cannot rollback settings user isolation while user-owned settings exist.'
            );
        }

        $hasDuplicateKeys = DB::table( 'settings' )
            ->select( 'key' )
            ->groupBy( 'key' )
            ->havingRaw( 'COUNT(*) > 1' )
            ->exists();

        if ( $hasDuplicateKeys ) {
            throw new RuntimeException(
                'Cannot restore the global settings key index because duplicate keys exist.'
            );
        }

        Schema::table( 'settings', static function ( Blueprint $table ) {
            $table->dropUnique( 'settings_user_id_key_unique' );
            $table->dropForeign( [ 'user_id' ] );
            $table->dropColumn( 'user_id' );
            $table->unique( 'key' );
        } );
    }
};
