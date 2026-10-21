<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('channel', 10);
            $table->string('locale', 5);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->timestamps();

            $table->unique(['group_id', 'key', 'channel', 'locale']);
        });

        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 10);
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('template_key', 50)->nullable();
            $table->string('status', 10)->default('queued');
            $table->string('provider', 30)->nullable();
            $table->string('provider_message_id', 100)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['group_id', 'status', 'created_at']);
        });

        Schema::create('inbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);
            $table->string('sender', 20);
            $table->text('body');
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reply_id')->nullable()->constrained('outbound_messages')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('driver', 30);
            $table->text('credentials')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['group_id', 'kind']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->nullableMorphs('subject');
            $table->string('description');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['group_id', 'created_at']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 30);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('visibility', 20)->default('members');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('compliance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_on');
            $table->string('recurrence', 10)->default('none');
            $table->foreignId('position_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('pending');
            $table->date('completed_on')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['compliance_items', 'documents', 'audit_logs', 'integrations', 'inbound_messages', 'outbound_messages', 'message_templates'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
