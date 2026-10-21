<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mgr_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('payout_amount')->default(0);
            $table->date('starts_on');
            $table->string('status', 20)->default('draft');
            $table->string('ballot_seed', 40)->nullable();
            $table->timestamp('balloted_at')->nullable();
            $table->foreignId('balloted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('mgr_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mgr_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->date('scheduled_on');
            $table->string('status', 20)->default('upcoming');
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('deferred_count')->default(0);
            $table->timestamps();

            $table->unique(['mgr_cycle_id', 'member_id']);
        });

        Schema::create('mgr_swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mgr_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requester_slot_id')->constrained('mgr_slots')->cascadeOnDelete();
            $table->foreignId('target_slot_id')->constrained('mgr_slots')->cascadeOnDelete();
            $table->string('status', 20)->default('pending_member');
            $table->string('reason')->nullable();
            $table->timestamp('member_responded_at')->nullable();
            $table->timestamp('chair_responded_at')->nullable();
            $table->foreignId('chair_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fundraisers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('beneficiary')->nullable();
            $table->unsignedBigInteger('target');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('show_contributors')->default(true);
            $table->foreignId('account_id')->nullable()->constrained();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fundraiser_pledges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fundraiser_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->unsignedBigInteger('amount');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('welfare_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dependant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('beneficiary_name');
            $table->string('relationship', 20);
            $table->string('event', 30);
            $table->text('description')->nullable();
            $table->date('event_on');
            $table->unsignedBigInteger('benefit_amount');
            $table->unsignedBigInteger('levy_per_member')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 30);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('share_basis', 20)->default('contribution');
            $table->foreignId('coordinator_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained();
            $table->timestamps();
        });

        Schema::create('project_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('contributed')->default(0);
            $table->date('joined_on');
            $table->timestamps();

            $table->unique(['project_id', 'member_id']);
        });

        Schema::create('project_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit', 20)->default('pcs');
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('unit_price');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('paid')->default(0);
            $table->string('buyer_name');
            $table->string('buyer_phone', 20)->nullable();
            $table->string('status', 20)->default('unpaid');
            $table->date('sold_on');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_item_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->integer('quantity');
            $table->unsignedBigInteger('unit_cost')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('project_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->unsignedBigInteger('amount');
            $table->date('entry_on');
            $table->string('description');
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'kind']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category', 30);
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('hire_rate')->nullable();
            $table->unsignedBigInteger('purchase_cost')->nullable();
            $table->date('acquired_on')->nullable();
            $table->string('condition', 20)->default('good');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('hires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hirer_name');
            $table->string('hirer_phone', 20)->nullable();
            $table->unsignedInteger('quantity');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedBigInteger('rate');
            $table->unsignedBigInteger('total');
            $table->unsignedBigInteger('paid')->default(0);
            $table->string('status', 20)->default('booked');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('kind', 20)->default('ordinary');
            $table->dateTime('scheduled_at');
            $table->string('venue')->nullable();
            $table->text('agenda')->nullable();
            $table->text('minutes')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('meeting_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->timestamps();

            $table->unique(['meeting_id', 'member_id']);
        });

        Schema::create('resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 30);
            $table->string('title');
            $table->text('body')->nullable();
            $table->date('adopted_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['resolutions', 'meeting_attendances', 'meetings', 'hires', 'assets', 'project_entries', 'stock_movements', 'sales', 'project_items', 'project_participants', 'projects', 'welfare_cases', 'fundraiser_pledges', 'fundraisers', 'mgr_swaps', 'mgr_slots', 'mgr_cycles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
