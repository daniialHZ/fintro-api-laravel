<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('name_fa', 100);
            $table->string('icon', 50)->default('🏦');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_custom')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 100);
            $table->string('name_fa', 100);
            $table->string('icon', 50)->default('💰');
            $table->string('type', 20);
            $table->boolean('is_default')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 20);
            $table->double('amount');
            $table->foreignId('source_id')->constrained('sources');
            $table->foreignId('category_id')->constrained('categories');
            $table->string('description_encrypted', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 200);
            $table->double('target_amount');
            $table->double('current_amount')->default(0);
            $table->date('deadline');
        });

        Schema::create('portfolio_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 100);
            $table->double('percentage');
        });

        Schema::create('onboarding_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->nullable()->constrained('users')->nullOnDelete();
            $table->string('investment_experience', 32)->nullable();
            $table->string('market_decline_reaction', 32)->nullable();
            $table->string('investment_horizon', 32)->nullable();
            $table->string('monthly_income_range', 32)->nullable();
            $table->string('saving_habit', 32)->nullable();
            $table->string('financial_anxiety', 32)->nullable();
            $table->double('risk_score')->nullable();
            $table->string('risk_level', 32)->nullable();
            $table->double('discipline_score')->nullable();
            $table->string('stress_level', 32)->nullable();
            $table->string('time_horizon_level', 32)->nullable();
            $table->string('income_capacity_level', 32)->nullable();
            $table->double('confidence')->nullable();
            $table->text('recommendations_json')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->integer('last_completed_step')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('wealth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 200);
            $table->string('type', 32);
            $table->double('amount');
            $table->double('quantity')->nullable();
            $table->string('unit', 50)->nullable();
            $table->date('purchase_date')->nullable();
            $table->double('purchase_price')->nullable();
            $table->string('notes_encrypted', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
        });

        Schema::create('invite_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('max_uses')->default(1);
            $table->integer('current_uses')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('prompt_type', 100);
            $table->text('prompt_text');
            $table->text('response_text')->nullable();
            $table->text('parsed_response')->nullable();
            $table->string('success', 10)->nullable();
            $table->text('error_message')->nullable();
            $table->double('response_time_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('anomaly_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('analysis_date');
            $table->text('anomalies_detected');
            $table->boolean('has_anomalies')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('health_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('analysis_date');
            $table->double('financial_health_score');
            $table->text('analysis_text');
            $table->text('recommendations');
            $table->text('summary')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_analytics');
        Schema::dropIfExists('anomaly_analytics');
        Schema::dropIfExists('ai_logs');
        Schema::dropIfExists('invite_codes');
        Schema::dropIfExists('wealth');
        Schema::dropIfExists('onboarding_profiles');
        Schema::dropIfExists('portfolio_targets');
        Schema::dropIfExists('goals');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('sources');
    }
};
