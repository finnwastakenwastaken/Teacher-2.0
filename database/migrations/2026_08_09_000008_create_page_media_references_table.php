<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which media each page references, extracted from its TipTap JSON body and
 * rebuilt every time the page is saved.
 *
 * The content column already contains this information, but only as a nested
 * document that would need a recursive jsonb walk to search. Two questions
 * get asked constantly and both need the *reverse* direction:
 *
 *   - may an anonymous visitor fetch this file? (it is published exactly
 *     when some page references it — see App\Support\MediaAccess)
 *   - which pages break if the owner deletes this file? (deletes block and
 *     report rather than cascading)
 *
 * Derived data, never authored: App\Support\PageContent::references() is the
 * only writer, so it cannot drift from the document it came from.
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
