<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // The banner across the top of a page, separate from anything
            // embedded in the body. Kept as a column rather than as a body
            // node because it is page furniture, not content: it renders
            // above the title and survives every edit of the body.
            //
            // restrictOnDelete, like every other reference to uploaded media
            // in this schema. Deleting an image that a page is using blocks
            // and reports the pages using it (the technical reference) rather
            // than silently leaving a page with a hole in it.
            $table->foreignId('hero_image_id')
                ->nullable()
                ->after('is_hidden')
                ->constrained('images')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hero_image_id');
        });
    }
};
