<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

final class StripeBilling
{
    private const EVENTS = ['checkout.session.completed', 'customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted', 'invoice.paid', 'invoice.payment_failed'];
    private const CLOSED = ['canceled', 'incomplete_expired'];

    public function __construct(private StripeBillingGateway $gateway, private EntityManagerInterface $em, private UserRepository $users, private ClockInterface $clock)
    {
    }

    public function checkout(User $owner): string
    {
        return $this->em->getConnection()->transactional(function () use ($owner): string {
            $this->em->refresh($owner, LockMode::PESSIMISTIC_WRITE);
            $customer = $owner->getStripeCustomerId();
            if (null === $customer) {
                if ($owner->getPaidUntil() > $this->clock->now()) {
                    throw new \DomainException('Ce compte bénéficie déjà du quota abonné.');
                }
                $this->gateway->monthlyPrice();
                $customer = $this->gateway->createCustomer($owner);
                $owner->linkStripeCustomer($customer);
                $this->em->flush();
            } else {
                $subscription = $this->gateway->subscription($customer);
                if ($subscription && !in_array($subscription['status'], self::CLOSED, true)) {
                    return $this->gateway->portal($customer);
                }
                $this->gateway->monthlyPrice();
            }

            return $this->gateway->checkout($customer);
        });
    }

    public function portal(User $owner): string
    {
        $customer = $owner->getStripeCustomerId() ?? throw new \DomainException('Ce compte n’a pas encore de facturation Stripe.');

        return $this->gateway->portal($customer);
    }

    /** @param array<string, mixed> $event */
    public function reconcile(array $event): void
    {
        $id = $event['id'] ?? null;
        $type = $event['type'] ?? null;
        $customer = $event['data']['object']['customer'] ?? null;
        if (false !== ($event['livemode'] ?? null) || !in_array($type, self::EVENTS, true)
            || !is_string($id) || !preg_match('/^evt_[A-Za-z0-9_]{1,90}$/D', $id)
            || !is_string($customer) || !preg_match('/^cus_[A-Za-z0-9]{1,60}$/D', $customer)) {
            return;
        }
        $owner = $this->users->findOneBy(['stripeCustomerId' => $customer]);
        if (!$owner) {
            return;
        }

        $this->em->getConnection()->transactional(function () use ($owner, $id, $customer): void {
            $this->em->refresh($owner, LockMode::PESSIMISTIC_WRITE);
            if ($owner->getStripeCustomerId() !== $customer) {
                return;
            }
            $inserted = $this->em->getConnection()->executeStatement(
                'INSERT INTO stripe_billing_event (id, received_at) VALUES (?, CURRENT_TIMESTAMP) ON CONFLICT (id) DO NOTHING',
                [$id],
            );
            if (!$inserted) {
                return;
            }

            // Fetch the current state while holding the owner lock, even for delayed events.
            $subscription = $this->gateway->subscription($customer);
            if (!$subscription) {
                return;
            }
            $status = $subscription['status'];
            $paidUntil = $owner->getPaidUntil();
            if (in_array($status, self::CLOSED, true)) {
                $paidUntil = null;
            } elseif ('active' === $status && null !== $subscription['paidThrough']) {
                $paidUntil = new \DateTimeImmutable('@'.$subscription['paidThrough']);
            }
            $owner->reconcileStripeSubscription($status, $subscription['ending'], $paidUntil);
            $this->em->flush();
        });
    }
}
