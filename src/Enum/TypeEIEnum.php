<?php

namespace App\Enum;

/**
 * Types d'événements indésirables (CDC §4.3).
 *
 * Liste fixe définie par la clinique, administrable par ROLE_ADMIN (UC-12).
 * Note : en V1 la liste est figée dans le code. La gestion dynamique
 * des types (ajout/désactivation via l'interface admin) est prévue en V2.
 */
enum TypeEIEnum: string
{
    case Chute             = 'chute';
    case ErreurMedicament  = 'erreur_medicament';
    case Infection         = 'infection';
    case Materiovigilance  = 'materiovigilance';
    case Autre             = 'autre';

    /**
     * Libellé lisible pour l'affichage dans le formulaire wizard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Chute            => 'Chute',
            self::ErreurMedicament => 'Erreur médicamenteuse',
            self::Infection        => 'Infection',
            self::Materiovigilance => 'Matériovigilance',
            self::Autre            => 'Autre',
        };
    }
}
