<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookkeeping for in-progress chunked uploads.
 *
 * Chunked upload is mandatory rather than a nicety: Cloudflare's Free and Pro
 * plans reject any request body over 100 MB, so a browser simply cannot POST
 * a lecture video in one piece through the tunnel. The browser slices the
 * file with Blob.slice() and sends ~20 MB at a time; this table tracks what a
 * given upload claims to be so the server can verify the reassembled result
 * instead of trusting it.
 *
 * The chunks themselves are files on the private disk under chunks/{ulid}/,
 * not rows here — the row records only what was promised, and completion
 * checks the promise against what actually arrived.
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
