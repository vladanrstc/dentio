<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('intervention_id')->nullable()->constrained('interventions')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->text('description')->nullable();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('usd');
            $table->string('status')->default('pending');
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->text('payment_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'pp_company_status_idx');
            $table->index(['patient_id', 'status'], 'pp_patient_status_idx');
            $table->index('appointment_id', 'pp_appointment_idx');
            $table->index('intervention_id', 'pp_intervention_idx');
            $table->index('stripe_payment_intent_id', 'pp_payment_intent_idx');
            $table->unique('stripe_checkout_session_id', 'pp_checkout_session_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_payments');
    }
};
