<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Someone asking for a login from the sign-up page. No user exists
        // until an official approves, so a waiting request cannot sign in.
        Schema::create('signup_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('id_number', 20)->nullable();
            $table->string('locale', 5)->default('en');
            $table->string('password');
            $table->string('status', 20)->default('pending');
            $table->string('reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_requests');
    }
};
