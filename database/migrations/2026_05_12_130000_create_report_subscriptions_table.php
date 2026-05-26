<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('report_key');
            $table->string('frequency')->default('off');
            $table->string('format')->default('csv');
            $table->json('filters')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'report_key']);
            $table->index(['frequency', 'next_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_subscriptions');
    }
};
