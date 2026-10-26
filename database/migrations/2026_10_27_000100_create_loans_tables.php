<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('source', 20);
            $table->string('status', 20);
            $table->unsignedBigInteger('principal');
            $table->string('purpose')->nullable();
            $table->unsignedSmallInteger('term_months')->nullable();
            $table->unsignedBigInteger('instalment')->nullable();
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->string('interest_method', 10)->default('reducing');
            $table->string('interest_period', 10)->default('month');
            $table->boolean('interest_paused')->default(false);
            $table->string('interest_note')->nullable();
            $table->date('next_interest_on')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();
            $table->date('closed_on')->nullable();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('resolution_reference', 60)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group_id', 'number']);
            $table->index(['group_id', 'status']);
        });

        // The loan's own book. Principal and interest are signed: a charge is
        // positive, a repayment or waiver negative, so a sum is the balance.
        Schema::create('loan_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->bigInteger('principal')->default(0);
            $table->bigInteger('interest')->default(0);
            $table->date('occurred_on');
            $table->string('period', 7)->nullable();
            $table->string('description');
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['loan_id', 'type', 'period']);
        });

        Schema::create('loan_guarantors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status', 10)->default('pending');
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'member_id']);
        });

        foreach (DB::table('groups')->pluck('id') as $groupId) {
            $exists = DB::table('accounts')->where('group_id', $groupId)->where('code', '1100')->exists();

            if (! $exists) {
                DB::table('accounts')->insert([
                    'group_id' => $groupId,
                    'code' => '1100',
                    'name' => 'Loans to members',
                    'kind' => 'asset',
                    'purpose' => 'loans',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Existing groups already have their positions; give the treasurer
        // and the chair the new loan permissions.
        $grants = ['treasurer' => 'loans.manage', 'chairperson' => 'loans.adjust'];

        foreach (DB::table('positions')->whereIn('slug', array_keys($grants))->get(['id', 'slug', 'permissions']) as $position) {
            $permissions = json_decode((string) $position->permissions, true) ?: [];

            if (! in_array($grants[$position->slug], $permissions, true)) {
                $permissions[] = $grants[$position->slug];
                DB::table('positions')->where('id', $position->id)->update(['permissions' => json_encode($permissions)]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_guarantors');
        Schema::dropIfExists('loan_transactions');
        Schema::dropIfExists('loans');
    }
};
