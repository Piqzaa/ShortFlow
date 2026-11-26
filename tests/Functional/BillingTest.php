<?php

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BillingTest extends WebTestCase
{
    private const EMAIL = 'stripe-billing@example.test';
    private const CUSTOMER = 'cus_TestBilling';
    private const PRICE = 'price_TestMonthly';
    private const SECRET = 'whsec_TestBillingOnly';
    private KernelBrowser $browser;
    private ?ClientInterface $previousTransport;
    private array $previousEnv;
    private array $previousServer;
    private int $checkoutCreates = 0;
    private int $subscriptionReads = 0;
    private array $subscriptions = [];
    private bool $openCheckout = false;
    private bool $stripeUnavailable = false;
    private bool $testDatabase = false;
    private string $eventPrefix;

    protected function setUp(): void
    {
        $this->previousEnv = $_ENV;
        $this->previousServer = $_SERVER;
        foreach ([
            'STRIPE_TEST_SECRET_KEY' => 'sk_test_Synthetic',
            'STRIPE_TEST_PRICE_ID' => self::PRICE,
            'STRIPE_TEST_WEBHOOK_SECRET' => self::SECRET,
            'STRIPE_TEST_PORTAL_CONFIGURATION_ID' => 'bpc_Fixture',
        ] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $this->eventPrefix = 'evt_VydTest_'.bin2hex(random_bytes(4)).'_';
        $this->previousTransport = ApiRequestor::httpClient();
        ApiRequestor::setHttpClient(new class($this->mockStripe(...)) implements ClientInterface {
            public function __construct(private \Closure $respond)
            {
            }

            public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null): array
            {
                return ($this->respond)($method, $absUrl, $headers, $params);
            }
        });
        $this->browser = static::createClient();
        $this->browser->disableReboot();
        self::assertSame('shortflow_test', $this->db()->fetchOne('SELECT current_database()'));
        $this->testDatabase = true;
        $this->db()->executeStatement('DELETE FROM app_user WHERE email = ?', [self::EMAIL]);
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = new User(self::EMAIL);
        $user->setPassword('unused-fixture');
        $user->verify();
        $em->persist($user);
        $em->flush();
        $this->browser->loginUser($user);
    }

    protected function tearDown(): void
    {
        if ($this->testDatabase) {
            $this->db()->executeStatement('DELETE FROM stripe_billing_event WHERE LEFT(id, ?) = ?', [strlen($this->eventPrefix), $this->eventPrefix]);
            $this->db()->executeStatement('DELETE FROM app_user WHERE email = ?', [self::EMAIL]);
        }
        ApiRequestor::setHttpClient($this->previousTransport);
        $_ENV = $this->previousEnv;
        $_SERVER = $this->previousServer;
        parent::tearDown();
    }

    public function testCheckoutRenewalCancellationAndExpiryStayBoundToVerifiedStripeEvents(): void
    {
        $account = '/studio/mon-compte?section=subscription';
        $crawler = $this->browser->request('GET', $account);
        self::assertSelectorTextContains('.subscription-current', 'Free');
        self::assertSelectorTextContains('.subscription-plan-paid', '9,00 €');
        self::assertSelectorTextContains('.subscription-plan.is-current', '500 Mo');
        $form = $crawler->selectButton('Choisir l’abonnement de test')->form();
        $this->browser->submit($form);
        self::assertResponseRedirects('https://checkout.stripe.com/c/pay/cs_test_Fixture', 303);
        $this->browser->submit($form);
        self::assertResponseRedirects('https://checkout.stripe.com/c/pay/cs_test_Fixture', 303);
        self::assertSame(1, $this->checkoutCreates);
        $this->browser->request('GET', $account.'&billing=checkout');
        self::assertSelectorTextContains('.subscription-current', 'Free');
        self::assertSelectorTextContains('[role="status"]', 'confirmation de Stripe');

        $firstPeriod = time() + 86400;
        $this->subscription('active', $firstPeriod);
        $this->webhook('first', 'invoice.paid', valid: false);
        self::assertResponseStatusCodeSame(400);
        self::assertNull($this->user()->getPaidUntil());
        $this->webhook('first', 'invoice.paid');
        self::assertResponseStatusCodeSame(204);
        $reads = $this->subscriptionReads;
        $this->webhook('first', 'invoice.paid');
        self::assertResponseStatusCodeSame(204);
        self::assertSame($reads, $this->subscriptionReads);
        self::assertSame($firstPeriod, $this->user()->getPaidUntil()?->getTimestamp());

        $crawler = $this->browser->request('GET', $account);
        self::assertSelectorTextContains('.subscription-current', 'Abonné');
        self::assertSelectorTextContains('.subscription-plan.is-current', '3 Go');
        self::assertSelectorNotExists('form[action$="/souscrire"]');
        $this->browser->submit($crawler->selectButton('Gérer mon abonnement')->form());
        self::assertResponseRedirects('https://billing.stripe.com/p/session/test_Fixture', 303);

        $nextPeriod = $firstPeriod + 30 * 86400;
        $this->subscription('past_due', $nextPeriod, paid: false);
        $this->webhook('paymentfailed', 'invoice.payment_failed');
        self::assertResponseStatusCodeSame(204);
        self::assertSame($firstPeriod, $this->user()->getPaidUntil()?->getTimestamp());
        $this->browser->request('GET', $account);
        self::assertSelectorTextContains('.subscription-overview', 'paiement demande une action');

        $this->subscription('active', $nextPeriod);
        $this->stripeUnavailable = true;
        $this->webhook('renewal', 'invoice.paid');
        self::assertResponseStatusCodeSame(503);
        $this->stripeUnavailable = false;
        $this->webhook('renewal', 'invoice.paid');
        self::assertResponseStatusCodeSame(204);
        self::assertSame($nextPeriod, $this->user()->getPaidUntil()?->getTimestamp());

        $this->subscription('active', $nextPeriod, ending: true);
        $this->webhook('ending', 'customer.subscription.updated');
        self::assertResponseStatusCodeSame(204);
        $this->browser->request('GET', $account);
        self::assertSelectorTextContains('.subscription-overview', 'Ton abonnement prend fin');
        self::assertSame($nextPeriod, $this->user()->getPaidUntil()?->getTimestamp());
        $this->webhook('oldstate', 'customer.subscription.deleted');
        self::assertResponseStatusCodeSame(204);
        self::assertSame($nextPeriod, $this->user()->getPaidUntil()?->getTimestamp());

        $this->subscription('canceled', $nextPeriod);
        $this->webhook('live', 'customer.subscription.deleted', live: true);
        self::assertResponseStatusCodeSame(204);
        self::assertSame($nextPeriod, $this->user()->getPaidUntil()?->getTimestamp());
        $this->webhook('end', 'customer.subscription.deleted');
        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->user()->getPaidUntil());
        $this->browser->request('GET', $account);
        self::assertSelectorTextContains('.subscription-plan.is-current', '500 Mo');
        self::assertSelectorTextContains('form[action$="/souscrire"] button', 'Me réabonner en test');
    }

    private function subscription(string $status, int $end, bool $paid = true, bool $ending = false): void
    {
        $this->subscriptions = [[
            'id' => 'sub_Fixture', 'object' => 'subscription', 'customer' => self::CUSTOMER, 'livemode' => false,
            'status' => $status, 'cancel_at_period_end' => $ending,
            'items' => ['object' => 'list', 'data' => [['id' => 'si_Fixture', 'current_period_end' => $end, 'price' => ['id' => self::PRICE]]]],
            'latest_invoice' => ['id' => 'in_Fixture', 'object' => 'invoice', 'status' => $paid ? 'paid' : 'open', 'lines' => [
                'object' => 'list', 'data' => [[
                    'period' => ['end' => $end],
                    'parent' => ['subscription_item_details' => ['subscription_item' => 'si_Fixture', 'proration' => false]],
                ]],
            ]],
        ]];
    }

    private function webhook(string $suffix, string $type, bool $valid = true, bool $live = false): void
    {
        $payload = json_encode(['id' => $this->eventPrefix.$suffix, 'object' => 'event', 'type' => $type, 'livemode' => $live, 'data' => ['object' => ['customer' => self::CUSTOMER]]], JSON_THROW_ON_ERROR);
        $time = time();
        $signature = hash_hmac('sha256', $time.'.'.$payload, $valid ? self::SECRET : 'invalid');
        $this->browser->request('POST', '/webhooks/stripe', server: ['HTTP_STRIPE_SIGNATURE' => "t=$time,v1=$signature"], content: $payload);
    }

    private function mockStripe(string $method, string $url, array $headers, array $params): array
    {
        self::assertStringStartsWith('https://api.stripe.com/v1/', $url);
        self::assertContains('Authorization: Bearer sk_test_Synthetic', $headers);
        $path = parse_url($url, PHP_URL_PATH);
        $checkout = ['id' => 'cs_test_Fixture', 'object' => 'checkout.session', 'livemode' => false, 'mode' => 'subscription', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_Fixture', 'metadata' => ['vydflow_price' => self::PRICE]];
        $body = match ($method.' '.$path) {
            'get /v1/prices/'.self::PRICE => ['id' => self::PRICE, 'object' => 'price', 'livemode' => false, 'active' => true, 'currency' => 'eur', 'unit_amount' => 900, 'recurring' => ['interval' => 'month', 'interval_count' => 1]],
            'post /v1/customers' => ['id' => self::CUSTOMER, 'object' => 'customer', 'livemode' => false],
            'get /v1/checkout/sessions' => ['object' => 'list', 'data' => $this->openCheckout ? [$checkout] : []],
            'post /v1/checkout/sessions' => $this->createCheckout($params, $checkout),
            'get /v1/subscriptions' => $this->listSubscriptions(),
            'get /v1/billing_portal/configurations/bpc_Fixture' => [
                'id' => 'bpc_Fixture', 'object' => 'billing_portal.configuration', 'livemode' => false, 'active' => true,
                'features' => ['subscription_cancel' => ['enabled' => true, 'mode' => 'at_period_end']],
            ],
            'post /v1/billing_portal/sessions' => ['object' => 'billing_portal.session', 'url' => 'https://billing.stripe.com/p/session/test_Fixture'],
            default => self::fail('Unexpected Stripe request '.$method.' '.$path),
        };
        $unavailable = $this->stripeUnavailable && '/v1/subscriptions' === $path;

        return [json_encode($unavailable ? ['error' => ['message' => 'Synthetic unavailable']] : $body, JSON_THROW_ON_ERROR), $unavailable ? 503 : 200, []];
    }

    private function createCheckout(array $params, array $checkout): array
    {
        self::assertSame(self::CUSTOMER, $params['customer']);
        self::assertSame([['price' => self::PRICE, 'quantity' => 1]], $params['line_items']);
        ++$this->checkoutCreates;
        $this->openCheckout = true;

        return $checkout;
    }

    private function listSubscriptions(): array
    {
        ++$this->subscriptionReads;

        return ['object' => 'list', 'data' => $this->subscriptions];
    }

    private function user(): User
    {
        return static::getContainer()->get(UserRepository::class)->findByEmail(self::EMAIL) ?? throw new \LogicException('Missing fixture');
    }

    private function db(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
