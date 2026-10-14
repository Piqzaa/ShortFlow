<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Only reveal confirmation status after Symfony has checked the password.
        if ($user instanceof User && !$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException('Confirme ton adresse email avant de te connecter.');
        }
    }
}
