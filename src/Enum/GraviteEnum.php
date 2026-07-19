<?php

namespace App\Enum;

/**
 * Niveaux de gravité d'un événement indésirable.
 *
 * Les valeurs Grave, Critique et Deces déclenchent automatiquement
 * le statut EIGS (isEIGS = true) sur la déclaration.
 *
 * Source : formulaire HAS EIGS — HAS/MSP/PCH/10/2017
 * Assistant en 3 questions (CDC §4.2) :
 *   Q1 : décès ?      → Deces
 *   Q2 : pronostic vital ? → Critique
 *   Q3 : déficit fonctionnel permanent ? → Grave
 *   Sinon → Modere ou Mineur
 */
enum GraviteEnum: string
{
    case Mineur   = 'mineur';
    case Modere   = 'modere';
    case Grave    = 'grave';
    case Critique = 'critique';
    case Deces    = 'deces';

    /**
     * Libellé lisible pour l'affichage frontend / sérialisation JSON.
     */
    public function label(): string
    {
        return match ($this) {
            self::Mineur   => 'Mineur',
            self::Modere   => 'Modéré',
            self::Grave    => 'Grave',
            self::Critique => 'Critique',
            self::Deces    => 'Décès',
        };
    }

    /**
     * Règle métier CDC §4.3 :
     * isEIGS = true si gravite IN (grave, critique, deces).
     * Calculé automatiquement — jamais saisi manuellement.
     */
    public function isEIGS(): bool
    {
        return match ($this) {
            self::Grave, self::Critique, self::Deces => true,
            default                                  => false,
        };
    }

    /**
     * Couleur du bandeau de feedback blameless (CDC §4.2).
     * Utilisé côté frontend pour le rendu visuel.
     */
    public function bandeauCouleur(): string
    {
        return match ($this) {
            self::Grave, self::Critique, self::Deces => 'red',
            self::Modere                             => 'orange',
            self::Mineur                             => 'green',
        };
    }
}
