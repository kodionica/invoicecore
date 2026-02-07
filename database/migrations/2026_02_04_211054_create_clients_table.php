<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create( 'clients', static function ( Blueprint $table ) {
            $table->id();
            $table->foreignId( 'company_id' )->constrained()->cascadeOnDelete();
            $table->string( 'name' );
            $table->tinyInteger( 'pib' )->nullable()->unique();
            $table->tinyInteger( 'mb' )->nullable()->unique();
            $table->string( 'address' )->nullable();
            $table->string( 'city' )->nullable();
            $table->string( 'country' )->nullable();
            $table->string( 'email' )->nullable();
            $table->string( 'phone' )->nullable();
            $table->timestamps();
        } );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists( 'clients' );
    }
};
