<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('kind', 10);
            $table->string('purpose', 20);
            $table->nullableMorphs('owner');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['group_id', 'code']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->date('entry_date');
            $table->string('description');
            $table->string('reference')->nullable();
            $table->nullableMorphs('source');
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['group_id', 'number']);
            $table->index(['group_id', 'entry_date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained();
            $table->foreignId('member_id')->nullable()->constrained();
            $table->unsignedBigInteger('debit')->default(0);
            $table->unsignedBigInteger('credit')->default(0);
            $table->string('memo')->nullable();

            $table->index(['account_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
