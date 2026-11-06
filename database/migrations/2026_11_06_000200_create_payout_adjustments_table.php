<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->unsignedBigInteger('amount');
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 200);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->foreignId('adjusted_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjusted_by');
        });

        Schema::dropIfExists('payout_adjustments');
    }
};
