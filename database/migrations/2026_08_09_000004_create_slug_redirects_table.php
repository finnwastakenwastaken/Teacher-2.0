<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            // Polymorphic, not a frozen destination path: resolving a
            // redirect always walks the CURRENT tree, so it stays correct
            // even if the target is renamed or moved again later.
            $table->morphs('redirectable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_redirects');
    }
};
