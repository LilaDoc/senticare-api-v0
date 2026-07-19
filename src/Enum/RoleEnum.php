<?php

namespace App\Enum;

/**
 * Rôles applicatifs SentiCare (CDC §3).
 *
 * Hiérarchie métier : ROLE_CHEF_POLE > ROLE_CADRE > ROLE_SOIGNANT
 * ROLE_ADMIN est orthogonal : gestion technique, aucun accès aux déclarations.
 *
 * Ces constantes sont stockées en JSON dans User.roles (colonne Doctrine).
 * Symfony gère l'héritage via security.yaml (role_hierarchy).
 */
enum RoleEnum: string
{
    case Soignant  = 'ROLE_SOIGNANT';
    case Cadre     = 'ROLE_CADRE';
    case ChefPole  = 'ROLE_CHEF_POLE';
    case Admin     = 'ROLE_ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::Soignant => 'Soignant',
            self::Cadre    => 'Cadre de santé',
            self::ChefPole => 'Chef de pôle',
            self::Admin    => 'Administrateur',
        };
    }
}
