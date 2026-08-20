<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained('topics')->restrictOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_hidden')->default(false);
            // TipTap JSON body. Populated once the page editor (task 5)
            // exists; nullable until then. Never HTML — see the technical reference, "Store
            // TipTap JSON, never HTML".
            $table->jsonb('content')->nullable();
            // Derived plain text of the above, kept in sync when the editor
            // saves. Feeds the search_vector trigger added in task 8.
            $table->text('content_text')->nullable();
            $table->timestamps();

            $table->unique(['topic_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
