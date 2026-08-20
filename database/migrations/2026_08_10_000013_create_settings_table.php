<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Site-wide settings the owner edits in the browser: the site title,
        // the logo and favicon, and the editable part of the homepage.
        //
        // Key/value rather than a one-row table with a column per setting.
        // Adding a setting is then a code change and a default, not a
        // migration, which matters because these are exactly the things that
        // get added one at a time over the life of the site.
        //
        // A missing row is not an error — App\Support\SiteSettings owns the
        // defaults, so a fresh install renders correctly with an empty table
        // and nothing has to be seeded.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            // jsonb, so a value can be a string, a number, an image id or a
            // whole TipTap document without a second column or a serialised
            // blob that only PHP can read.
            $table->jsonb('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
