<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('debt_people', function (Blueprint $table): void {
            $table->foreignId('shared_with_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('share_status', 24)->nullable()->after('shared_with_user_id');
            $table->timestamp('share_requested_at')->nullable()->after('share_status');
            $table->timestamp('share_responded_at')->nullable()->after('share_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('debt_people', function (Blueprint $table): void {
            $table->dropForeign(['shared_with_user_id']);
            $table->dropColumn([
                'shared_with_user_id',
                'share_status',
                'share_requested_at',
                'share_responded_at',
            ]);
        });
    }
};
