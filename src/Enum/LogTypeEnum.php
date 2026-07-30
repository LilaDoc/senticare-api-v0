<?php

namespace App\Enum;

/**
 * Types d'événements tracés dans le journal d'audit (CDC §6.3, UC-11).
 *
 * Trois catégories citées par le CDC — connexions, soumissions, actions
 * sensibles (gestion des comptes/pôles/services) — déclinées ici par action
 * précise pour permettre un filtrage fin côté admin.
 */
enum LogTypeEnum: string
{
    // Connexions
    case LoginSuccess = 'login_success';
    case LoginFailure = 'login_failure';

    // Soumissions
    case DeclarationSubmitted = 'declaration_submitted';

    // Actions sensibles — comptes utilisateurs
    case UserCreated = 'user_created';
    case UserDeactivated = 'user_deactivated';
    case UserReactivated = 'user_reactivated';

    // Actions sensibles — structure clinique
    case PoleCreated = 'pole_created';
    case PoleUpdated = 'pole_updated';
    case PoleDeactivated = 'pole_deactivated';
    case ServiceCreated = 'service_created';
    case ServiceUpdated = 'service_updated';
    case ServiceDeactivated = 'service_deactivated';

    public function label(): string
    {
        return match ($this) {
            self::LoginSuccess => 'Connexion réussie',
            self::LoginFailure => 'Échec de connexion',
            self::DeclarationSubmitted => 'Déclaration soumise',
            self::UserCreated => 'Compte créé',
            self::UserDeactivated => 'Compte désactivé',
            self::UserReactivated => 'Compte réactivé',
            self::PoleCreated => 'Pôle créé',
            self::PoleUpdated => 'Pôle modifié',
            self::PoleDeactivated => 'Pôle désactivé',
            self::ServiceCreated => 'Service créé',
            self::ServiceUpdated => 'Service modifié',
            self::ServiceDeactivated => 'Service désactivé',
        };
    }
}
