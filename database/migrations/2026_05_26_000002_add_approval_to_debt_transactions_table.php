<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_transactions', function (Blueprint $table): void {
            $table->string('status', 24)->default('approved')->after('signed_amount');
            $table->foreignId('requested_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->after('requested_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable()->after('approved_by_user_id');
            $table->index(['debt_person_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('debt_transactions', function (Blueprint $table): void {
            $table->dropIndex(['debt_person_id', 'status']);
            $table->dropForeign(['requested_by_user_id']);
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn([
                'status',
                'requested_by_user_id',
                'approved_by_user_id',
                'responded_at',
            ]);
        });
    }
};
