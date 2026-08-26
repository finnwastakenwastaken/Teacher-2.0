<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookkeeping for in-progress chunked uploads. Chunking is mandatory, not a
 * nicety: Cloudflare Free/Pro reject bodies over 100 MB, so the browser
 * slices with Blob.slice() into ~20 MB pieces. This table records what an
 * upload claims to be so completion can verify the reassembled result rather
 * than trust it; the chunks themselves live as files under chunks/{ulid}/.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_uploads', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // Who started this upload. Deliberately the user and not the
            // session: a multi-gigabyte upload can easily outlive a session
            // rotation, and scoping to the session would leave the owner
            // unable to finish their own upload halfway through.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            // What the client said it was sending. Recorded for diagnostics
            // only — the real type is detected from the assembled bytes.
            $table->string('declared_mime')->nullable();
            $table->unsignedBigInteger('total_bytes');
            $table->unsignedInteger('chunk_bytes');
            $table->unsignedInteger('total_chunks');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_uploads');
    }
};
