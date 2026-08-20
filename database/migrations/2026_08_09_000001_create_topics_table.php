<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('topics')->restrictOnDelete();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('title');
            $table->string('slug');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });

        // Postgres treats every NULL as distinct from every other NULL, so the
        // composite unique index above never fires between two root topics
        // (parent_id is null on all of them). This partial index is the
        // root-level equivalent of that constraint.
        DB::statement(
            'create unique index topics_root_slug_unique on topics (slug) where parent_id is null'
        );

        DB::statement(
            'alter table topics add constraint topics_depth_check check (depth between 0 and 2)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
