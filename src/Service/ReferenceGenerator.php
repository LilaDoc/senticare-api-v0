<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Génère la référence lisible d'une déclaration : DCL-2026-0042.
 *
 * L'identifiant technique reste l'UUID v4 (CDC §6.2, anti-énumération), mais un
 * UUID n'est pas citable : impossible de dicter au téléphone
 * « 019fcfd1-37fa-7199-980c-fbc28f67da1d » ou de l'inscrire sur un compte rendu
 * de revue. Cette référence courte sert exclusivement à l'affichage et à
 * l'échange humain — aucune route ne l'accepte en paramètre.
 *
 * ⚠️ Pourquoi une séquence PostgreSQL et pas un COUNT(*) + 1 : deux soignants
 * qui déclarent simultanément liraient le même total et généreraient la MÊME
 * référence. La contrainte d'unicité ferait alors échouer l'une des deux
 * déclarations — un signalement perdu pour un problème de numérotation.
 * `nextval()` est atomique et ne peut pas produire deux fois la même valeur,
 * même sous concurrence.
 *
 * Conséquence assumée de la séquence : le compteur ne se remet pas à zéro au
 * changement d'année (la première déclaration de 2027 peut être
 * DCL-2027-0891). Les références restent uniques et chronologiques, ce qui est
 * le seul besoin réel ; une remise à zéro annuelle imposerait un verrou ou une
 * table de compteurs pour un gain purement cosmétique.
 */
class ReferenceGenerator
{
    public const SEQUENCE = 'declaration_reference_seq';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function generate(?\DateTimeImmutable $date = null): string
    {
        $numero = (int) $this->connection->fetchOne(
            sprintf('SELECT nextval(%s)', $this->connection->quote(self::SEQUENCE))
        );

        return sprintf('DCL-%s-%04d', ($date ?? new \DateTimeImmutable())->format('Y'), $numero);
    }

    /**
     * Remet la séquence à zéro — réservé aux fixtures, pour qu'un rechargement
     * du jeu de démonstration produise toujours les mêmes références.
     */
    public function reset(): void
    {
        $this->connection->executeStatement(
            sprintf('ALTER SEQUENCE %s RESTART WITH 1', self::SEQUENCE)
        );
    }
}
