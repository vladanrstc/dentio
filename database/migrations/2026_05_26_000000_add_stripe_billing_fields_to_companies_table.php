<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->boolean('payments_enabled')->default(false)->after('created_by_user_id');
            $table->string('stripe_customer_id')->nullable()->after('payments_enabled');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            $table->string('subscription_status')->nullable()->after('stripe_subscription_id');
            $table->string('subscription_plan')->nullable()->after('subscription_status');
            $table->timestamp('subscription_current_period_end')->nullable()->after('subscription_plan');
            $table->boolean('subscription_cancel_at_period_end')->default(false)->after('subscription_current_period_end');

            $table->index('stripe_customer_id', 'companies_stripe_customer_idx');
            $table->index('stripe_subscription_id', 'companies_stripe_subscription_idx');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropIndex('companies_stripe_customer_idx');
            $table->dropIndex('companies_stripe_subscription_idx');
            $table->dropColumn([
                'payments_enabled',
                'stripe_customer_id',
                'stripe_subscription_id',
                'subscription_status',
                'subscription_plan',
                'subscription_current_period_end',
                'subscription_cancel_at_period_end',
            ]);
        });
    }
};
