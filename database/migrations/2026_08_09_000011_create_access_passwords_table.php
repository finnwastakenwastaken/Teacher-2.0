<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Named, reusable passwords.
 *
 * Deliberately not one password per page. A teacher protects "5 VWO" once and
 * applies that same record to a topic branch and to loose pages elsewhere;
 * unlocking any one of them unlocks all of them, and changing the password
 * changes it everywhere at once. A per-page secret would mean handing every
 * class a different string per page, which nobody would keep up.
 *
 * The password is applied to a topic (covering its whole subtree) or to a
 * single page. Resolution walks up to the nearest ancestor that has one —
 * see App\Support\AccessControl.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_passwords', function (Blueprint $table) {
            $table->id();
            // What the owner calls it, e.g. "5 VWO". Shown to visitors on the
            // unlock prompt, so that someone who holds two passwords knows
            // which one is being asked for — a teacher reuses these across
            // classes, and an unlabelled prompt is a guessing game. It is
            // therefore not a place for anything sensitive; the admin guide
            // says so.
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
