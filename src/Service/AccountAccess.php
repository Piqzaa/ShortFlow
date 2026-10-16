<?php

namespace App\Service;

use App\Entity\AccountToken;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AccountAccess
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        #[Autowire('%env(APP_PUBLIC_URL)%')] private string $publicUrl,
    ) {
    }

    public function register(string $email, string $password): void
    {
        if (null !== $this->users->findByEmail($email)) {
            return;
        }
        $user = new User($email);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();
        $this->sendToken($user, AccountToken::VERIFY);
    }

    public function changePassword(User $user, #[\SensitiveParameter] string $currentPassword, #[\SensitiveParameter] string $newPassword): bool
    {
        return $this->em->wrapInTransaction(function () use ($user, $currentPassword, $newPassword): bool {
            $this->em->refresh($user, LockMode::PESSIMISTIC_WRITE);
            if (!$this->hasher->isPasswordValid($user, $currentPassword)) {
                return false;
            }
            $user->setPassword($this->hasher->hashPassword($user, $newPassword));
            $this->em->getConnection()->executeStatement('UPDATE account_token SET consumed_at = CURRENT_TIMESTAMP WHERE owner_id = :owner AND purpose = :purpose', ['owner' => $user->getId(), 'purpose' => AccountToken::RESET]);

            return true;
        });
    }

    public function requestToken(string $email, string $purpose): void
    {
        $user = $this->users->findByEmail($email);
        if (null === $user || (AccountToken::VERIFY === $purpose && $user->isVerified())) {
            return;
        }
        $this->sendToken($user, $purpose);
    }

    private function sendToken(User $user, string $purpose): void
    {
        $raw = bin2hex(random_bytes(32));
        $this->em->persist(new AccountToken($user, hash('sha256', $raw), $purpose));
        $this->em->flush();
        $reset = AccountToken::RESET === $purpose;
        // Trusted origin, independent of Host; fragments never reach HTTP access logs.
        $link = rtrim($this->publicUrl, '/').$this->urls->generate($reset ? 'app_reset_password' : 'app_verify_email').'#token='.$raw;
        $subject = $reset ? 'Choisis ton nouveau mot de passe VydFlow' : 'Confirme ton adresse email VydFlow';
        $text = $subject."\n\n".$link."\n\nCode à copier si nécessaire :\n".$raw."\n\n".($reset ? 'Ce lien est valable 30 minutes.' : 'Ce lien est valable 24 heures.')." Il ne fonctionne qu’une fois.\nSi tu n’es pas à l’origine de cette demande, ignore cet email.";
        $this->mailer->send((new Email())->from('VydFlow <bonjour@vydflow.test>')->to($user->getEmail())->subject($subject)->text($text));
    }

    public function consumeToken(string $raw, string $purpose, ?string $password = null): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $raw)) {
            return false;
        }

        return $this->em->wrapInTransaction(function () use ($raw, $purpose, $password): bool {
            $token = $this->em->getRepository(AccountToken::class)->findOneBy(['tokenHash' => hash('sha256', $raw)]);
            if (null === $token) {
                return false;
            }
            $user = $token->getOwner();
            // Serialize account changes before locking tokens to avoid crossed-reset deadlocks.
            $this->em->refresh($user, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($token, LockMode::PESSIMISTIC_WRITE);
            if (!$token->isUsable($purpose)) {
                return false;
            }
            if (AccountToken::RESET === $purpose) {
                if (null === $password) {
                    return false;
                }
                $user->setPassword($this->hasher->hashPassword($user, $password));
                $this->em->getConnection()->executeStatement('UPDATE account_token SET consumed_at = CURRENT_TIMESTAMP WHERE owner_id = :owner AND purpose = :purpose', ['owner' => $user->getId(), 'purpose' => AccountToken::RESET]);
            } else {
                $user->verify();
            }
            $token->consume();

            return true;
        });
    }
}
