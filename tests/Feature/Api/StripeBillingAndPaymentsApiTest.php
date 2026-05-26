<?php

namespace Tests\Feature\Api;

use App\Mail\PatientPaymentLinkMail;
use App\Mail\SubscriptionActivatedMail;
use App\Models\Company;
use App\Models\Intervention;
use App\Models\Patient;
use App\Models\PatientPayment;
use App\Models\User;
use App\Services\Contracts\StripeGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StripeBillingAndPaymentsApiTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripeGateway $stripe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stripe = new FakeStripeGateway;
        $this->app->instance(StripeGatewayInterface::class, $this->stripe);
        config([
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.monthly_price_id' => 'price_monthly_test',
            'services.stripe.yearly_price_id' => 'price_yearly_test',
            'services.stripe.currency' => 'usd',
        ]);
    }

    public function test_platform_admin_can_enable_company_payments(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_PLATFORM_ADMIN,
            'company_id' => null,
        ]);
        [$company] = $this->companyWithAdmin(paymentsEnabled: false);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/companies/{$company->id}/payment-settings", [
            'payments_enabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $company->id)
            ->assertJsonPath('data.payments_enabled', true);

        $this->assertTrue($company->fresh()->payments_enabled);
    }

    public function test_company_user_can_list_payments_for_own_patient(): void
    {
        [$company, $admin] = $this->companyWithAdmin();
        $patient = $this->patient($company);
        $intervention = $this->intervention($company, $patient);
        $ownPayment = $this->pendingPayment($company, $patient, $admin, $intervention);

        [$otherCompany, $otherAdmin] = $this->companyWithAdmin('other');
        $otherPatient = $this->patient($otherCompany, ['email' => 'other-patient@example.com']);
        $otherIntervention = $this->intervention($otherCompany, $otherPatient);
        $otherPayment = $this->pendingPayment($otherCompany, $otherPatient, $otherAdmin, $otherIntervention);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/company/payments?patient_id={$patient->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $ownPayment->id)
            ->assertJsonPath('data.0.patient_id', $patient->id)
            ->assertJsonPath('data.0.appointment_id', null)
            ->assertJsonPath('data.0.intervention_id', $intervention->id)
            ->assertJsonPath('data.0.amount', 5000)
            ->assertJsonPath('data.0.currency', 'usd')
            ->assertJsonPath('data.0.status', PatientPayment::STATUS_PENDING)
            ->assertJsonPath('data.0.description', 'Intervencija')
            ->assertJsonPath('data.0.payment_url', 'https://checkout.stripe.test/session')
            ->assertJsonMissing(['id' => $otherPayment->id]);
    }

    public function test_company_user_does_not_see_payments_for_patient_from_other_company(): void
    {
        [, $admin] = $this->companyWithAdmin();
        [$otherCompany, $otherAdmin] = $this->companyWithAdmin('other');
        $otherPatient = $this->patient($otherCompany, ['email' => 'other-patient@example.com']);
        $otherIntervention = $this->intervention($otherCompany, $otherPatient);
        $this->pendingPayment($otherCompany, $otherPatient, $otherAdmin, $otherIntervention);

        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/company/payments?patient_id={$otherPatient->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_user_cannot_list_company_payments(): void
    {
        $this->getJson('/api/v1/company/payments')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_company_cannot_create_patient_payment_when_payments_are_disabled(): void
    {
        Mail::fake();
        [$company, $admin] = $this->companyWithAdmin(paymentsEnabled: false);
        $patient = $this->patient($company);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/payments', [
            'patient_id' => $patient->id,
            'amount' => 5000,
            'currency' => 'usd',
            'description' => 'Intervencija',
        ])
            ->assertForbidden()
            ->assertJsonPath('message', __('errors.payments_disabled'));

        $this->assertDatabaseCount('patient_payments', 0);
        Mail::assertNothingSent();
    }

    public function test_company_can_create_patient_payment_when_enabled_without_marking_it_paid(): void
    {
        Mail::fake();
        [$company, $admin] = $this->companyWithAdmin();
        $patient = $this->patient($company, ['email' => 'patient@example.com']);
        $intervention = $this->intervention($company, $patient, [
            'total_cost' => 100,
            'paid_amount' => 0,
        ]);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/payments', [
            'patient_id' => $patient->id,
            'intervention_id' => $intervention->id,
            'amount' => 5000,
            'currency' => 'usd',
            'description' => 'Intervencija',
        ])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.intervention_id', $intervention->id)
            ->assertJsonPath('data.amount', 5000)
            ->assertJsonPath('data.status', PatientPayment::STATUS_PENDING)
            ->assertJsonPath('data.payment_url', 'https://checkout.stripe.test/session-1');

        $this->assertDatabaseHas('patient_payments', [
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'intervention_id' => $intervention->id,
            'status' => PatientPayment::STATUS_PENDING,
        ]);
        $this->assertSame(0.0, (float) $intervention->fresh()->paid_amount);
        Mail::assertSent(PatientPaymentLinkMail::class);
    }

    public function test_company_cannot_create_payment_for_patient_from_other_company(): void
    {
        Mail::fake();
        [, $admin] = $this->companyWithAdmin();
        [$otherCompany] = $this->companyWithAdmin('other');
        $otherPatient = $this->patient($otherCompany);

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/payments', [
            'patient_id' => $otherPatient->id,
            'amount' => 5000,
            'currency' => 'usd',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.patient_id.0', __('errors.payment_patient_not_found'));

        Mail::assertNothingSent();
    }

    public function test_checkout_session_completed_webhook_marks_payment_paid(): void
    {
        Mail::fake();
        [$company, $admin] = $this->companyWithAdmin();
        $patient = $this->patient($company);
        $intervention = $this->intervention($company, $patient, [
            'total_cost' => 100,
            'paid_amount' => 20,
        ]);
        $payment = $this->pendingPayment($company, $patient, $admin, $intervention);

        $this->postJson('/api/v1/stripe/webhook', $this->checkoutCompletedEvent($payment))
            ->assertOk()
            ->assertJsonPath('data.received', true);

        $payment->refresh();
        $this->assertSame(PatientPayment::STATUS_PAID, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('pi_test_paid', $payment->stripe_payment_intent_id);
        $this->assertSame(70.0, (float) $intervention->fresh()->paid_amount);
    }

    public function test_payment_webhook_is_idempotent(): void
    {
        Mail::fake();
        [$company, $admin] = $this->companyWithAdmin();
        $patient = $this->patient($company);
        $intervention = $this->intervention($company, $patient, [
            'total_cost' => 100,
            'paid_amount' => 0,
        ]);
        $payment = $this->pendingPayment($company, $patient, $admin, $intervention);
        $event = $this->checkoutCompletedEvent($payment);

        $this->postJson('/api/v1/stripe/webhook', $event)->assertOk();
        $this->postJson('/api/v1/stripe/webhook', $event)->assertOk();

        $this->assertSame(50.0, (float) $intervention->fresh()->paid_amount);
    }

    public function test_company_can_create_subscription_checkout_session(): void
    {
        [$company, $admin] = $this->companyWithAdmin();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/billing/checkout', [
            'plan' => 'monthly',
        ])
            ->assertOk()
            ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.test/session-1');

        $this->assertSame('cus_test_1', $company->fresh()->stripe_customer_id);
    }

    public function test_checkout_session_completed_retrieves_subscription_and_stores_period_end(): void
    {
        Mail::fake();
        [$company, $admin] = $this->companyWithAdmin();
        $company->forceFill([
            'stripe_customer_id' => 'cus_test_1',
        ])->save();
        $this->stripe->retrievedSubscriptions['sub_from_checkout'] = [
            'id' => 'sub_from_checkout',
            'customer' => 'cus_test_1',
            'status' => 'active',
            'current_period_end' => 1_779_744_000,
            'cancel_at_period_end' => true,
            'metadata' => [
                'plan' => 'yearly',
            ],
        ];

        $this->postJson('/api/v1/stripe/webhook', [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'mode' => 'subscription',
                    'customer' => 'cus_test_1',
                    'subscription' => 'sub_from_checkout',
                    'status' => 'complete',
                    'metadata' => [
                        'company_id' => (string) $company->id,
                        'plan' => 'monthly',
                    ],
                ],
            ],
        ])->assertOk();

        $company->refresh();
        $this->assertSame('sub_from_checkout', $company->stripe_subscription_id);
        $this->assertSame('active', $company->subscription_status);
        $this->assertSame('yearly', $company->subscription_plan);
        $this->assertSame(1_779_744_000, $company->subscription_current_period_end?->timestamp);
        $this->assertTrue($company->subscription_cancel_at_period_end);
        Mail::assertSent(SubscriptionActivatedMail::class, 1);
        $periodEnd = $company->subscription_current_period_end?->toIso8601String();

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/company/billing')
            ->assertOk()
            ->assertJsonPath('data.subscription_current_period_end', $periodEnd);

        $platformAdmin = User::factory()->create([
            'role' => User::ROLE_PLATFORM_ADMIN,
            'company_id' => null,
        ]);

        Sanctum::actingAs($platformAdmin);

        $this->getJson("/api/v1/admin/companies/{$company->id}")
            ->assertOk()
            ->assertJsonPath('data.subscription_current_period_end', $periodEnd);
    }

    public function test_billing_checkout_returns_safe_error_when_stripe_secret_is_missing(): void
    {
        config(['services.stripe.secret' => null]);
        [, $admin] = $this->companyWithAdmin();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/billing/checkout', [
            'plan' => 'monthly',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('errors.stripe_not_configured'))
            ->assertJsonMissing(['message' => 'api_key cannot be the empty string']);
    }

    public function test_billing_checkout_returns_safe_error_when_monthly_price_id_is_missing(): void
    {
        config(['services.stripe.monthly_price_id' => null]);
        [, $admin] = $this->companyWithAdmin();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/billing/checkout', [
            'plan' => 'monthly',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('errors.stripe_not_configured'))
            ->assertJsonMissing(['message' => 'api_key cannot be the empty string']);
    }

    public function test_billing_checkout_returns_safe_error_when_yearly_price_id_is_missing(): void
    {
        config(['services.stripe.yearly_price_id' => null]);
        [, $admin] = $this->companyWithAdmin();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/billing/checkout', [
            'plan' => 'yearly',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', __('errors.stripe_not_configured'))
            ->assertJsonMissing(['message' => 'api_key cannot be the empty string']);
    }

    public function test_subscription_webhook_updates_company_status(): void
    {
        Mail::fake();
        [$company, $admin] = $this->companyWithAdmin();
        $company->forceFill([
            'stripe_customer_id' => 'cus_test_1',
        ])->save();

        $this->postJson('/api/v1/stripe/webhook', [
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_test_1',
                    'customer' => 'cus_test_1',
                    'status' => 'active',
                    'current_period_end' => 1_779_744_000,
                    'cancel_at_period_end' => false,
                    'metadata' => [
                        'plan' => 'monthly',
                    ],
                ],
            ],
        ])->assertOk();
        $this->postJson('/api/v1/stripe/webhook', [
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_test_1',
                    'customer' => 'cus_test_1',
                    'status' => 'active',
                    'current_period_end' => 1_779_744_000,
                    'cancel_at_period_end' => false,
                    'metadata' => [
                        'plan' => 'monthly',
                    ],
                ],
            ],
        ])->assertOk();

        $company->refresh();
        $this->assertSame('sub_test_1', $company->stripe_subscription_id);
        $this->assertSame('active', $company->subscription_status);
        $this->assertSame('monthly', $company->subscription_plan);
        $this->assertSame(1_779_744_000, $company->subscription_current_period_end?->timestamp);
        $this->assertFalse($company->subscription_cancel_at_period_end);
        Mail::assertSent(SubscriptionActivatedMail::class, 1);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/company/billing')
            ->assertOk()
            ->assertJsonPath('data.subscription_status', 'active')
            ->assertJsonPath('data.subscription_plan', 'monthly')
            ->assertJsonPath(
                'data.subscription_current_period_end',
                $company->subscription_current_period_end?->toIso8601String()
            );
    }

    public function test_invoice_paid_webhook_updates_subscription_current_period_end(): void
    {
        Mail::fake();
        [$company] = $this->companyWithAdmin();
        $company->forceFill([
            'stripe_customer_id' => 'cus_test_1',
            'stripe_subscription_id' => 'sub_test_1',
        ])->save();

        $this->postJson('/api/v1/stripe/webhook', [
            'type' => 'invoice.paid',
            'data' => [
                'object' => [
                    'customer' => 'cus_test_1',
                    'subscription' => 'sub_test_1',
                    'paid' => true,
                    'lines' => [
                        'data' => [[
                            'period' => [
                                'end' => 1_779_744_000,
                            ],
                        ]],
                    ],
                ],
            ],
        ])->assertOk();

        $company->refresh();
        $this->assertSame('active', $company->subscription_status);
        $this->assertSame(1_779_744_000, $company->subscription_current_period_end?->timestamp);
    }

    public function test_customer_portal_endpoint_returns_url(): void
    {
        [, $admin] = $this->companyWithAdmin();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/billing/portal')
            ->assertOk()
            ->assertJsonPath('data.portal_url', 'https://billing.stripe.test/session');
    }

    public function test_customer_portal_returns_safe_error_when_stripe_secret_is_missing(): void
    {
        config(['services.stripe.secret' => '']);
        [, $admin] = $this->companyWithAdmin();

        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/company/billing/portal')
            ->assertUnprocessable()
            ->assertJsonPath('message', __('errors.stripe_not_configured'))
            ->assertJsonMissing(['message' => 'api_key cannot be the empty string']);
    }

    /**
     * @return array{Company, User}
     */
    private function companyWithAdmin(string $suffix = 'main', bool $paymentsEnabled = true): array
    {
        $company = Company::query()->create([
            'name' => "Dentio {$suffix}",
            'address' => 'Test adresa',
            'email' => "office-{$suffix}@example.com",
            'phone' => '060123456',
            'payments_enabled' => $paymentsEnabled,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Company',
            'last_name' => 'Admin',
            'role' => User::ROLE_COMPANY_ADMIN,
        ]);

        return [$company, $admin];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function patient(Company $company, array $overrides = []): Patient
    {
        return Patient::query()->create(array_merge([
            'company_id' => $company->id,
            'first_name' => 'Petar',
            'last_name' => 'Petrovic',
            'email' => 'patient@example.com',
            'manual_status' => Patient::STATUS_ACTIVE,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function intervention(Company $company, Patient $patient, array $overrides = []): Intervention
    {
        return Intervention::query()->create(array_merge([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'title' => 'Intervencija',
            'intervention_date' => '2026-05-26',
            'total_cost' => 100,
            'paid_amount' => 0,
        ], $overrides));
    }

    private function pendingPayment(Company $company, Patient $patient, User $admin, Intervention $intervention): PatientPayment
    {
        return PatientPayment::query()->create([
            'company_id' => $company->id,
            'patient_id' => $patient->id,
            'intervention_id' => $intervention->id,
            'created_by_user_id' => $admin->id,
            'type' => PatientPayment::TYPE_INTERVENTION,
            'description' => 'Intervencija',
            'amount' => 5000,
            'currency' => 'usd',
            'status' => PatientPayment::STATUS_PENDING,
            'stripe_checkout_session_id' => "cs_test_{$patient->id}_{$intervention->id}",
            'payment_url' => 'https://checkout.stripe.test/session',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutCompletedEvent(PatientPayment $payment): array
    {
        return [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_paid',
                    'mode' => 'payment',
                    'payment_intent' => 'pi_test_paid',
                    'customer' => 'cus_test_paid',
                    'metadata' => [
                        'payment_id' => (string) $payment->id,
                    ],
                ],
            ],
        ];
    }
}

class FakeStripeGateway implements StripeGatewayInterface
{
    /**
     * @var array<string, array<string, mixed>>
     */
    public array $retrievedSubscriptions = [];

    private int $checkoutCount = 0;

    private int $customerCount = 0;

    public function createCheckoutSession(array $params): array
    {
        $this->checkoutCount++;

        return [
            'id' => 'cs_test_'.$this->checkoutCount,
            'url' => 'https://checkout.stripe.test/session-'.$this->checkoutCount,
            'mode' => $params['mode'] ?? null,
            'customer' => $params['customer'] ?? null,
            'payment_intent' => ($params['mode'] ?? null) === 'payment' ? 'pi_test_'.$this->checkoutCount : null,
            'subscription' => ($params['mode'] ?? null) === 'subscription' ? 'sub_test_'.$this->checkoutCount : null,
        ];
    }

    public function createCustomer(array $params): array
    {
        $this->customerCount++;

        return [
            'id' => 'cus_test_'.$this->customerCount,
        ];
    }

    public function createPortalSession(array $params): array
    {
        return [
            'url' => 'https://billing.stripe.test/session',
        ];
    }

    public function constructWebhookEvent(string $payload, ?string $signature): array
    {
        return json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
    }

    public function retrieveSubscription(string $subscriptionId): array
    {
        return $this->retrievedSubscriptions[$subscriptionId] ?? [
            'id' => $subscriptionId,
            'customer' => 'cus_test_1',
            'status' => 'active',
            'current_period_end' => 1_779_744_000,
            'cancel_at_period_end' => false,
            'metadata' => [
                'plan' => 'monthly',
            ],
        ];
    }
}
