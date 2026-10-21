<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('registration_number')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('location')->nullable();
            $table->string('paybill', 20)->nullable();
            $table->string('paybill_account', 40)->nullable();
            $table->string('currency', 3)->default('KES');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('member_number', 20);
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('id_number', 20)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('joined_on');
            $table->string('status', 20)->default('active');
            $table->string('nominee_name')->nullable();
            $table->string('nominee_phone', 20)->nullable();
            $table->string('nominee_relationship', 40)->nullable();
            $table->string('nominee_id_number', 20)->nullable();
            $table->boolean('notify_sms')->default(true);
            $table->boolean('notify_email')->default(true);
            $table->boolean('reminders_opt_out')->default(false);
            $table->boolean('show_name_publicly')->default(false);
            $table->string('locale', 5)->default('en');
            $table->date('exit_notice_on')->nullable();
            $table->date('exited_on')->nullable();
            $table->string('exit_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'member_number']);
            $table->unique(['group_id', 'phone']);
            $table->index(['group_id', 'status']);
        });

        Schema::create('dependants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship', 20);
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->json('permissions');
            $table->boolean('is_official')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('max_holders')->nullable();
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();

            $table->unique(['group_id', 'slug']);
        });

        Schema::create('position_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_acting')->default(false);
            $table->string('resolution_reference')->nullable();
            $table->string('ended_reason')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'starts_on', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_terms');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('dependants');
        Schema::dropIfExists('members');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });
        Schema::dropIfExists('groups');
    }
};
