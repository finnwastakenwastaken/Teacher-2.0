<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which media each page references, extracted from its TipTap JSON body and
 * rebuilt on every save. The content column has this, but only as a nested
 * document needing a recursive jsonb walk; this answers the reverse direction
 * cheaply — is a file public (some page references it, App\Support\MediaAccess),
 * and what breaks if it's deleted (deletes block, not cascade). Derived data:
 * App\Support\PageContent::references() is the only writer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_media_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->morphs('referenceable');
            $table->timestamps();

            $table->unique(['page_id', 'referenceable_type', 'referenceable_id'], 'page_media_references_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_media_references');
    }
};
