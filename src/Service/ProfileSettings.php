<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ProfileSettings
{
    public function __construct(private EntityManagerInterface $em, private ProfileAvatarStorage $avatars, private PublicationLock $lock)
    {
    }

    public function updateProfile(User $user, string $name, ?UploadedFile $photo, bool $remove): void
    {
        $name = trim($name);
        if (mb_strlen($name) > 80 || ($photo && $remove)) {
            throw new \RuntimeException('Choisis une nouvelle photo ou son retrait, puis vérifie le nom affiché.');
        }
        $new = $photo ? $this->avatars->store($photo) : null;
        $old = null;
        try {
            $this->lock->run((int) $user->getId(), function () use ($user, $name, $new, $remove, &$old): void {
                $this->em->wrapInTransaction(function () use ($user, $name, $new, $remove, &$old): void {
                    $this->em->refresh($user, LockMode::PESSIMISTIC_WRITE);
                    $user->setDisplayName($name);
                    if ($new || $remove) {
                        $old = $user->getAvatarName();
                        $user->setAvatarName($new);
                    }
                });
            });
        } catch (\Throwable) {
            $this->avatars->remove($new);
            throw new \RuntimeException('Le profil n’a pas pu être enregistré. Réessaie dans quelques instants.');
        }
        $this->avatars->remove($old);
    }

    public function updateTimezone(User $user, string $timezone): void
    {
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new \RuntimeException('Choisis un fuseau horaire valide.');
        }
        $this->lock->run((int) $user->getId(), function () use ($user, $timezone): void {
            $this->em->wrapInTransaction(function () use ($user, $timezone): void {
                $this->em->refresh($user, LockMode::PESSIMISTIC_WRITE);
                $user->setTimezone($timezone);
            });
        });
    }
}
