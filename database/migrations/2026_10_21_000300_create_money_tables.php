<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->string('purpose', 30);
            $table->nullableMorphs('payable');
            $table->unsignedBigInteger('amount');
            $table->string('phone', 20)->nullable();
            $table->string('payer_name')->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('external_id', 100)->nullable();
            $table->string('receipt_number', 50)->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            $table->boolean('show_name')->default(false);
            $table->timestamp('received_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->unsignedBigInteger('meeting_id')->nullable()->index();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained();
            $table->timestamp('allocated_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'channel', 'external_id']);
            $table->index(['group_id', 'status', 'created_at']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30);
            $table->string('event_type', 50);
            $table->string('external_id', 100)->nullable();
            $table->boolean('signature_valid');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['provider', 'external_id', 'event_type']);
        });

        Schema::create('statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('matched')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('period', 7)->nullable();
            $table->string('description');
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('paid')->default(0);
            $table->json('breakdown')->nullable();
            $table->date('due_on');
            $table->string('status', 10)->default('open');
            $table->nullableMorphs('source');
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('waived_reason')->nullable();
            $table->timestamp('fined_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status', 'due_on']);
            $table->index(['group_id', 'type', 'period']);
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('type', 30);
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('amount');
            $table->foreignId('account_id')->constrained();
            $table->foreignId('payee_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('payee_name');
            $table->string('payee_phone', 20)->nullable();
            $table->string('description');
            $table->nullableMorphs('payable');
            $table->string('batch', 40)->nullable()->index();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('paid_method', 20)->nullable();
            $table->string('paid_reference', 100)->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained();
            $table->timestamps();

            $table->unique(['group_id', 'number']);
        });

        Schema::create('payout_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->string('position_name');
            $table->string('decision', 10);
            $table->string('comment')->nullable();
            $table->timestamps();

            $table->unique(['payout_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_approvals');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('dues');
        Schema::dropIfExists('statement_imports');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('payments');
    }
};
