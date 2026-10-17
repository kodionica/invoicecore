<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_revisions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('acting_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type');
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_fields')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_revisions') && DB::table('invoice_revisions')->exists()) {
            throw new RuntimeException('Cannot rollback invoice revisions while audit history contains data.');
        }

        Schema::dropIfExists('invoice_revisions');
    }
};
