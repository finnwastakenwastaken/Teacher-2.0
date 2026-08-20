<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The education tracks a download can be tagged with.
 *
 * Seeded, never hardcoded. Dutch secondary education is not a fixed list —
 * schools combine tracks (VMBO-GT), split them, or use their own names for
 * a stream — so the owner has to be able to rename, reorder, add and remove
 * these without a code change. Nothing in the application may branch on a
 * particular level existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('education_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('education_levels');
    }
};
