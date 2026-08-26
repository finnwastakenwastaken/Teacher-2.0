<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Site-wide settings (title, logo, favicon, homepage copy). Key/value
        // rather than a column per setting, so adding one is a code change
        // and a default, not a migration. A missing row isn't an error —
        // App\Support\SiteSettings owns the defaults.
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            // jsonb so a value can be a string, number, image id or a whole
            // TipTap document without a second column.
            $table->jsonb('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
