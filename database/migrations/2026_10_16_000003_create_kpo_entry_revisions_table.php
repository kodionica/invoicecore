<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpo_entry_revisions', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('kpo_entry_id')->constrained('kpo_entries')->restrictOnDelete();
            $table->foreignId('acting_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type');
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->boolean('manual_edit_overwritten')->default(false);
            $table->uuid('operation_id')->nullable()->index();
            $table->timestamps();

            $table->index(['kpo_entry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        if (DB::table('kpo_entry_revisions')->exists()) {
            throw new RuntimeException('Cannot rollback KPO entry revisions while audit history exists.');
        }

        Schema::dropIfExists('kpo_entry_revisions');
    }
};
