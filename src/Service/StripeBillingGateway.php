<?php

namespace App\Service;

use App\Entity\User;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class StripeBillingGateway
{
    private const API_VERSION = '2025-06-30.basil';

    public function __construct(
        #[Autowire('%env(STRIPE_TEST_SECRET_KEY)%')] private string $testKey,
        #[Autowire('%env(STRIPE_TEST_PRICE_ID)%')] private string $priceId,
        #[Autowire('%env(STRIPE_TEST_WEBHOOK_SECRET)%')] private string $webhookSecret,
        #[Autowire('%env(APP_PUBLIC_URL)%')] private string $origin,
        #[Autowire('%kernel.secret%')] private string $appSecret,
    ) {
    }

    public function enabled(): bool
    {
        return 1 === preg_match('/^(sk|rk)_test_[a-zA-Z0-9]+$/D', $this->testKey)
            && 1 === preg_match('/^price_[a-zA-Z0-9]+$/D', $this->priceId)
            && 1 === preg_match('/^whsec_[A-Za-z0-9]+$/D', $this->webhookSecret);
    }

    public function monthlyPrice(): int
    {
        $price = $this->client()->prices->retrieve($this->priceId)->toArray();
        if (false !== ($price['livemode'] ?? null) || !($price['active'] ?? false) || 'eur' !== ($price['currency'] ?? null)
            || 'month' !== ($price['recurring']['interval'] ?? null) || 1 !== ($price['recurring']['interval_count'] ?? null)
            || !is_int($price['unit_amount'] ?? null) || $price['unit_amount'] <= 0) {
            throw new \DomainException('Configure un prix mensuel de test en euros.');
        }

        return $price['unit_amount'];
    }

    public function createCustomer(User $user): string
    {
        $key = hash_hmac('sha256', 'stripe-test-customer:'.$user->getId(), $this->appSecret);
        $customer = $this->client()->customers->create(['email' => $user->getEmail()], ['idempotency_key' => $key]);
        if (false !== $customer->livemode || !preg_match('/^cus_[A-Za-z0-9]{1,60}$/D', $customer->id)) {
            throw new \DomainException('Client Stripe de test invalide.');
        }

        return $customer->id;
    }

    public function checkout(string $customer): string
    {
        $client = $this->client();
        foreach ($client->checkout->sessions->all(['customer' => $customer, 'status' => 'open', 'limit' => 10])->data as $open) {
            if ('subscription' === $open->mode && $this->priceId === ($open->metadata->vydflow_price ?? null) && false === $open->livemode) {
                return $this->validatedUrl($open->url, 'checkout.stripe.com');
            }
        }
        $price = $this->priceId;
        $session = $client->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $customer,
            'payment_method_types' => ['card'],
            'line_items' => [['price' => $price, 'quantity' => 1]],
            'metadata' => ['vydflow_price' => $price],
            'success_url' => $this->returnUrl().'&billing=checkout',
            'cancel_url' => $this->returnUrl().'&billing=cancelled',
        ]);
        if (false !== $session->livemode) {
            throw new \DomainException('Session Stripe de test invalide.');
        }

        return $this->validatedUrl($session->url, 'checkout.stripe.com');
    }

    public function portal(string $customer): string
    {
        $portal = $this->client()->billingPortal;
        $configuration = $portal->configurations->all(['active' => true, 'is_default' => true, 'limit' => 1])->data[0] ?? null;
        if (!$configuration || false !== $configuration->livemode || !($configuration->features->subscription_cancel->enabled ?? false)
            || 'at_period_end' !== ($configuration->features->subscription_cancel->mode ?? null)) {
            throw new \DomainException('Configure le portail de test avec une annulation en fin de période.');
        }
        $session = $portal->sessions->create(['customer' => $customer, 'configuration' => $configuration->id, 'return_url' => $this->returnUrl()]);

        return $this->validatedUrl($session->url, 'billing.stripe.com');
    }

    /** @return array{status: string, ending: bool, paidThrough: ?int}|null */
    public function subscription(string $customer): ?array
    {
        $subscriptions = $this->client()->subscriptions->all([
            'customer' => $customer, 'status' => 'all', 'limit' => 25, 'expand' => ['data.latest_invoice'],
        ]);
        foreach ($subscriptions->data as $subscription) {
            $row = $subscription->toArray();
            if ($customer !== ($row['customer'] ?? null) || false !== ($row['livemode'] ?? null)) {
                continue;
            }
            foreach ($row['items']['data'] ?? [] as $item) {
                if ($this->priceId !== ($item['price']['id'] ?? null)) {
                    continue;
                }
                $invoice = $row['latest_invoice'] ?? null;
                $paidThrough = null;
                if (is_array($invoice) && 'paid' === ($invoice['status'] ?? null)) {
                    foreach ($invoice['lines']['data'] ?? [] as $line) {
                        $details = $line['parent']['subscription_item_details'] ?? [];
                        $end = $line['period']['end'] ?? null;
                        if (isset($item['id'], $item['current_period_end']) && ($details['subscription_item'] ?? null) === $item['id'] && !($details['proration'] ?? true) && is_int($end)) {
                            $paidThrough = max($paidThrough ?? 0, min($end, (int) $item['current_period_end']));
                        }
                    }
                }

                $cancelAt = $row['cancel_at'] ?? null;
                if (null !== $paidThrough && is_int($cancelAt)) {
                    $paidThrough = min($paidThrough, $cancelAt);
                }

                return ['status' => $row['status'], 'ending' => !empty($row['cancel_at_period_end']) || is_int($cancelAt), 'paidThrough' => $paidThrough];
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function event(string $payload, string $signature): array
    {
        if (!$this->enabled()) {
            throw new \DomainException('Stripe test est indisponible.');
        }

        return Webhook::constructEvent($payload, $signature, $this->webhookSecret)->toArray();
    }

    private function client(): StripeClient
    {
        if (!$this->enabled()) {
            throw new \DomainException('Stripe test est indisponible.');
        }

        return new StripeClient(['api_key' => $this->testKey, 'stripe_version' => self::API_VERSION, 'max_network_retries' => 1]);
    }

    private function returnUrl(): string
    {
        return rtrim($this->origin, '/').'/studio/mon-compte?section=subscription';
    }

    private function validatedUrl(?string $url, string $host): string
    {
        $parts = parse_url($url ?? '');
        if (!$url || 'https' !== ($parts['scheme'] ?? '') || $host !== ($parts['host'] ?? '') || isset($parts['user']) || isset($parts['port'])) {
            throw new \DomainException('Stripe n’a pas retourné de lien valide.');
        }

        return $url;
    }
}
