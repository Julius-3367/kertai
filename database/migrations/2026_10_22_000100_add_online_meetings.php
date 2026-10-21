<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->string('online_link', 500)->nullable()->after('venue');
            $table->unsignedSmallInteger('duration_minutes')->default(120)->after('online_link');
            $table->timestamp('reminded_at')->nullable()->after('status');
        });

        // Lets the sender attach things it builds at send time, such as a
        // meeting's calendar file.
        Schema::table('outbound_messages', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('template_key');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropColumn(['online_link', 'duration_minutes', 'reminded_at']);
        });

        Schema::table('outbound_messages', function (Blueprint $table) {
            $table->dropColumn('meta');
        });
    }
};
