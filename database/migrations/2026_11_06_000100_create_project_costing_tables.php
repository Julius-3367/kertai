<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_plots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('location', 120)->nullable();
            $table->decimal('size', 10, 2);
            $table->string('size_unit', 10)->default('acres');
            $table->string('tenure', 20);
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('owner_name', 120)->nullable();
            $table->string('owner_phone', 20)->nullable();
            $table->unsignedBigInteger('lease_amount')->nullable();
            $table->string('lease_per', 10)->nullable();
            $table->date('lease_starts_on')->nullable();
            $table->date('lease_ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
        });

        Schema::create('project_seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('open');
            $table->decimal('yield_quantity', 12, 2)->nullable();
            $table->string('yield_unit', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('project_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_season_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['project_season_id', 'category']);
        });

        Schema::create('project_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_season_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_plot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 30);
            $table->string('description', 200);
            $table->decimal('quantity', 12, 2);
            $table->string('unit', 20);
            $table->unsignedBigInteger('unit_cost');
            $table->unsignedBigInteger('amount');
            $table->date('incurred_on');
            $table->string('supplier_name', 120)->nullable();
            $table->string('supplier_phone', 20)->nullable();
            $table->string('receipt_ref', 60)->nullable();
            $table->string('labour_type', 20)->nullable();
            $table->unsignedInteger('workers')->nullable();
            $table->decimal('days', 6, 2)->nullable();
            $table->string('paid_by', 20);
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'project_season_id']);
            $table->index(['project_id', 'category']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('project_season_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });

        Schema::table('project_entries', function (Blueprint $table) {
            $table->foreignId('project_cost_id')->nullable()->after('sale_id')->constrained()->nullOnDelete();
        });

        // Expenses raised before costing existed become cost lines, so the
        // cost register starts complete. They can be given a category later.
        DB::table('payouts')
            ->where('type', 'project_expense')
            ->where('payable_type', 'project')
            ->orderBy('id')
            ->each(function (object $payout) {
                $project = DB::table('projects')->where('id', $payout->payable_id)->value('name');
                $description = $project && str_starts_with($payout->description, "{$project}: ")
                    ? substr($payout->description, strlen($project) + 2)
                    : $payout->description;

                DB::table('project_costs')->insert([
                    'project_id' => $payout->payable_id,
                    'category' => 'other',
                    'description' => mb_substr($description, 0, 200),
                    'quantity' => 1,
                    'unit' => 'item',
                    'unit_cost' => $payout->amount,
                    'amount' => $payout->amount,
                    'incurred_on' => substr((string) $payout->created_at, 0, 10),
                    'supplier_name' => $payout->payee_name,
                    'supplier_phone' => $payout->payee_phone,
                    'paid_by' => 'fund',
                    'payout_id' => $payout->id,
                    'recorded_by' => $payout->requested_by,
                    'created_at' => $payout->created_at,
                    'updated_at' => $payout->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('project_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_cost_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_season_id');
        });

        Schema::dropIfExists('project_costs');
        Schema::dropIfExists('project_budget_lines');
        Schema::dropIfExists('project_seasons');
        Schema::dropIfExists('project_plots');
    }
};
