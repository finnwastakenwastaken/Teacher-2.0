<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('images', function (Blueprint $table) {
            $table->id();
            // Public-facing identifier. Routes bind on this rather than the
            // auto-increment id so media URLs do not advertise how many
            // files exist or let anyone walk the library by counting up.
            $table->ulid('ulid')->unique();
            // Relative to the `local` (private) disk root. Never public.
            $table->string('path')->unique();
            $table->string('alt_text');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size_bytes');
            $table->string('mime');
            $table->string('original_filename');
            $table->timestamps();
        });

        // Alt text is required on every image (the technical reference). NOT NULL
        // alone would still accept an empty string, which is exactly as
        // useless to a screen reader as no attribute at all — so require
        // actual content. Also enforced in the Form Request and the
        // TypeScript type; this is the layer that catches a seeder, a
        // console command or a future bulk import.
        DB::statement(
            'alter table images add constraint images_alt_text_not_blank check (length(btrim(alt_text)) > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('images');
    }
};
