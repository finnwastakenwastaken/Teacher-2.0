<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            // See the images table for why routes bind on a ULID.
            $table->ulid('ulid')->unique();
            // Relative to the `local` (private) disk root. Never public.
            $table->string('path')->unique();
            $table->string('kind');
            $table->string('mime');
            $table->unsignedBigInteger('size_bytes');
            $table->string('original_filename');
            $table->timestamps();

            $table->index('kind');
        });

        DB::statement(
            "alter table media_files add constraint media_files_kind_check check (kind in ('document', 'video'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
