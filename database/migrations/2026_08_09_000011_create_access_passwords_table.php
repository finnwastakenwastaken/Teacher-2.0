<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Named, reusable passwords — deliberately not one per page. A teacher
 * protects "5 VWO" once and applies that record to a topic branch and to
 * loose pages; unlocking any one unlocks all, and changing it changes it
 * everywhere. Applies to a topic (whole subtree) or a single page; resolution
 * walks up to the nearest ancestor that has one (App\Support\AccessControl).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_passwords', function (Blueprint $table) {
            $table->id();
            // E.g. "5 VWO" — shown to visitors on the unlock prompt so anyone
            // holding two passwords knows which is being asked for. Not a
            // place for anything sensitive.
            $table->string('name')->unique();
            $table->string('password_hash');
            $table->timestamps();
        });

        // Restricts rather than nulls: dropping a password that still guards
        // pages would silently publish them. The admin panel makes the owner
        // detach it first and says where it is in use.
        Schema::table('topics', function (Blueprint $table) {
            $table->foreignId('access_password_id')->nullable()->after('is_hidden')
                ->constrained()->restrictOnDelete();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->foreignId('access_password_id')->nullable()->after('is_hidden')
                ->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_password_id');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropConstrainedForeignId('access_password_id');
        });

        Schema::dropIfExists('access_passwords');
    }
};
