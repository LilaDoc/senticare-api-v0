<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Enum\RoleEnum;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Contrôle d'accès par périmètre sur la gestion des comptes (CDC §3, UC-10, UC-12).
 *
 * - Chef de pôle : crée/consulte/désactive les comptes soignants et cadres de son pôle uniquement.
 * - Admin        : crée les comptes chefs de pôle, peut désactiver n'importe quel compte.
 */
class UserVoter extends Voter
{
    public const VIEW = 'USER_VIEW';
    public const CREATE = 'USER_CREATE';
    public const EDIT = 'USER_EDIT';
    public const DEACTIVATE = 'USER_DEACTIVATE';
    public const REACTIVATE = 'USER_REACTIVATE';

    private const ATTRIBUTES = [self::VIEW, self::CREATE, self::EDIT, self::DEACTIVATE, self::REACTIVATE];

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, self::ATTRIBUTES, true)) {
            return false;
        }

        // CREATE se vote sans sujet : le compte cible n'existe pas encore.
        if (self::CREATE === $attribute) {
            return null === $subject;
        }

        return $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $currentUser),
            self::CREATE => $this->canCreate($currentUser),
            self::EDIT => $this->canEdit($subject, $currentUser),
            self::DEACTIVATE => $this->canDeactivate($subject, $currentUser),
            self::REACTIVATE => $this->canReactivate($subject, $currentUser),
            default => false,
        };
    }

    /**
     * Chef de pôle : comptes rattachés à un service de son pôle.
     * Admin : comptes chefs de pôle uniquement (pas de vision sur soignants/cadres).
     */
    private function canView(User $target, User $currentUser): bool
    {
        if ($this->hasRole($currentUser, RoleEnum::Admin)) {
            // L'admin ne voit QUE les chefs de pôle (CDC §3) — pas n'importe quel compte.
            return $this->hasRole($target, RoleEnum::ChefPole);
        }

        if ($this->hasRole($currentUser, RoleEnum::ChefPole)) {
            // Limité à son propre pôle, pas "n'importe quel compte".
            return $this->sharePole($currentUser, $target);
        }

        return false;
    }

    /**
     * ROLE_CHEF_POLE ou ROLE_ADMIN uniquement. Le rôle *cible* du compte créé
     * (soignant/cadre pour un chef de pôle, chef de pôle pour un admin) est
     * vérifié dans UserManager::create(), pas ici — le Voter ne connaît pas
     * encore le payload de la requête.
     */
    private function canCreate(User $currentUser): bool
    {
        return $this->hasRole($currentUser, RoleEnum::ChefPole) || $this->hasRole($currentUser, RoleEnum::Admin);
    }

    /**
     * Même périmètre que canDeactivate() : admin -> tout compte, chef de pôle -> son pôle.
     * Ne pas permettre de modifier l'email (identifiant de connexion) ni le rôle
     * ici — c'est une question de logique métier (UserManager), pas d'autorisation.
     */
    private function canEdit(User $target, User $currentUser): bool
    {
        if ($this->hasRole($currentUser, RoleEnum::Admin)) {
            return true;
        }

        if ($this->hasRole($currentUser, RoleEnum::ChefPole)) {
            return $this->sharePole($currentUser, $target);
        }

        return false;
    }


    /**
     * Admin : n'importe quel compte.
     * Chef de pôle : comptes de son pôle uniquement.
     */
    private function canDeactivate(User $target, User $currentUser): bool
{
        if ($this->hasRole($currentUser, RoleEnum::Admin)) {
            return true;
        }

        if ($this->hasRole($currentUser, RoleEnum::ChefPole)) {
            return $this->sharePole($currentUser, $target);
        }

        return false;
    }


    /**
     * Réactivation d'un compte désactivé — CDC §5.1 : "désactivation SANS
     * suppression" implique que ce soit réversible. Même périmètre que canDeactivate().
     */
    private function canReactivate(User $target, User $currentUser): bool
{
        if ($this->hasRole($currentUser, RoleEnum::Admin)) {
            return true;
        }

        if ($this->hasRole($currentUser, RoleEnum::ChefPole)) {
            return $this->sharePole($currentUser, $target);
        }

        return false;
    }


    /**
     * Vrai si $chefPole et $target ont au moins un service dans le même pôle
     * (convention documentée dans docs/ROADMAP.md — pas de relation directe
     * User -> Pole dans le modèle actuel).
     */
    private function sharePole(User $chefPole, User $target): bool
    {
        foreach ($chefPole->getServices() as $chefPoleService) {
            foreach ($target->getServices() as $targetService) {
                if ($chefPoleService->getPole() === $targetService->getPole()) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasRole(User $user, RoleEnum $role): bool
    {
        return in_array($role->value, $user->getRoles(), true);
    }
}
