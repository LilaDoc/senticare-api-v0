<?php

namespace App\Enum;

/**
 * Cycle de vie d'une déclaration d'EI (CDC §4.4).
 *
 * Transitions autorisées :
 *   brouillon   → soumise     (par le déclarant : UC-05)
 *   brouillon   → abandonnee  (par le déclarant : UC-04 — irréversible)
 *   soumise     → en_analyse  (par cadre ou chef de pôle)
 *   en_analyse  → cloturee    (par cadre ou chef de pôle)
 *   cloturee    → transmise_has (EIGS uniquement — hors périmètre V1)
 *
 * Le statut initial à la création est TOUJOURS brouillon.
 */
enum StatutEnum: string
{
    case Brouillon    = 'brouillon';
    case Soumise      = 'soumise';
    case EnAnalyse    = 'en_analyse';
    case Cloturee     = 'cloturee';
    case TransmiseHAS = 'transmise_has';
    case Abandonnee   = 'abandonnee';

    /**
     * Libellé lisible pour l'affichage frontend / sérialisation JSON.
     */
    public function label(): string
    {
        return match ($this) {
            self::Brouillon    => 'Brouillon',
            self::Soumise      => 'Soumise',
            self::EnAnalyse    => 'En analyse',
            self::Cloturee     => 'Clôturée',
            self::TransmiseHAS => 'Transmise HAS',
            self::Abandonnee   => 'Abandonnée',
        };
    }

    /**
     * Transitions autorisées depuis ce statut.
     * Utilisé par le service métier pour valider les changements de statut.
     *
     * @return list<self>
     */
    public function transitionsAutorisees(): array
    {
        return match ($this) {
            self::Brouillon    => [self::Soumise, self::Abandonnee],
            self::Soumise      => [self::EnAnalyse],
            self::EnAnalyse    => [self::Cloturee],
            self::Cloturee     => [self::TransmiseHAS],
            // États terminaux : aucune transition possible
            self::TransmiseHAS,
            self::Abandonnee   => [],
        };
    }

    /**
     * Indique si la déclaration est encore modifiable par le déclarant.
     * Seul le brouillon peut être édité (CDC §4.4).
     */
    public function estModifiable(): bool
    {
        return $this === self::Brouillon;
    }
}
