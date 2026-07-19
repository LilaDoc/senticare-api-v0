<?php

namespace App\Security\Voter;

use App\Entity\Pole;
use App\Entity\Service;
use App\Entity\User;
use App\Enum\RoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Contrôle d'accès par périmètre sur les services médicaux (CDC UC-13, US-1.2).
 *
 * - Admin        : gère n'importe quel service, tous pôles confondus.
 * - Chef de pôle : gère uniquement les services de son propre pôle.
 *
 * Contrairement à UserVoter::CREATE (où le compte cible n'existe pas encore),
 * ici le pôle de destination EST connu au moment de la création (résolu par le
 * contrôleur depuis le payload avant d'appeler denyAccessUnlessGranted()) — le
 * sujet de CREATE est donc directement ce Pole, pas null.
 */
class ServiceVoter extends Voter
{
    public const VIEW = 'SERVICE_VIEW';
    public const CREATE = 'SERVICE_CREATE';
    public const EDIT = 'SERVICE_EDIT';
    public const DEACTIVATE = 'SERVICE_DEACTIVATE';

    private const ATTRIBUTES = [self::VIEW, self::CREATE, self::EDIT, self::DEACTIVATE];

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, self::ATTRIBUTES, true)) {
            return false;
        }

        if (self::CREATE === $attribute) {
            return $subject instanceof Pole;
        }

        return $subject instanceof Service;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::CREATE => $this->canCreate($subject, $currentUser),
            self::VIEW, self::EDIT, self::DEACTIVATE => $this->canManage($subject, $currentUser),
            default => false,
        };
    }

    /**
     * Admin : peut créer un service dans n'importe quel pôle.
     * Chef de pôle : uniquement dans son propre pôle.
     */
    private function canCreate(Pole $targetPole, User $currentUser): bool
    {
        if ($this->hasRole($currentUser, RoleEnum::Admin)) {
            return true;
        }

        if ($this->hasRole($currentUser, RoleEnum::ChefPole)) {
            return $this->poleBelongsToChefPole($targetPole, $currentUser);
        }

        return false;
    }

    /**
     * VIEW / EDIT / DEACTIVATE partagent la même règle de périmètre :
     * admin -> tout service ; chef de pôle -> services de son propre pôle uniquement.
     */
    private function canManage(Service $service, User $currentUser): bool
    {
        if ($this->hasRole($currentUser, RoleEnum::Admin)) {
            return true;
        }

        if ($this->hasRole($currentUser, RoleEnum::ChefPole)) {
            return $this->poleBelongsToChefPole($service->getPole(), $currentUser);
        }

        return false;
    }

    /**
     * Vrai si $pole correspond au pôle d'au moins un des services auxquels
     * $chefPole est rattaché (convention documentée dans docs/ROADMAP.md — pas
     * de relation directe User -> Pole dans le modèle actuel).
     */
    private function poleBelongsToChefPole(?Pole $pole, User $chefPole): bool
    {
        foreach ($chefPole->getServices() as $service) {
            if ($service->getPole() === $pole) {
                return true;
            }
        }

        return false;
    }

    private function hasRole(User $user, RoleEnum $role): bool
    {
        return in_array($role->value, $user->getRoles(), true);
    }
}
