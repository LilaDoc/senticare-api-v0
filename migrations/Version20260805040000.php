<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute la référence lisible des déclarations (DCL-2026-0042).
 *
 * L'UUID reste l'identifiant technique (CDC §6.2) ; cette référence sert
 * uniquement à l'affichage et à l'échange humain — un UUID ne se dicte pas au
 * téléphone et ne s'inscrit pas sur un compte rendu de revue.
 *
 * La numérotation s'appuie sur une séquence PostgreSQL plutôt que sur un
 * COUNT(*) + 1 : deux déclarations simultanées liraient le même total et
 * généreraient la même référence, ce qui ferait échouer l'une des deux sur la
 * contrainte d'unicité. `nextval()` est atomique.
 */
final class Version20260805040000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute declaration.reference (DCL-AAAA-NNNN) et sa séquence.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Migration prévue pour PostgreSQL (utilise une séquence native).'
        );

        $this->addSql('CREATE SEQUENCE declaration_reference_seq INCREMENT BY 1 MINVALUE 1 START 1');

        // Colonne d'abord nullable : les lignes déjà en base n'ont pas encore
        // de référence, on ne peut pas poser NOT NULL avant de les numéroter.
        $this->addSql('ALTER TABLE declaration ADD reference VARCHAR(20) DEFAULT NULL');

        // Rattrapage des déclarations déjà en base : chacune consomme un numéro
        // de la séquence créée ci-dessus. On réutilise donc exactement le même
        // mécanisme que ReferenceGenerator, plutôt qu'un calcul parallèle.
        //
        // Effet de bord voulu : la séquence se retrouve calée toute seule après
        // la dernière référence attribuée, sans avoir à la repositionner.
        $this->addSql(<<<'SQL'
            UPDATE declaration
            SET reference = 'DCL-'
                || to_char(created_at, 'YYYY')
                || '-'
                || lpad(nextval('declaration_reference_seq')::text, 4, '0')
            SQL);

        $this->addSql('ALTER TABLE declaration ALTER reference SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DECLARATION_REFERENCE ON declaration (reference)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_DECLARATION_REFERENCE');
        $this->addSql('ALTER TABLE declaration DROP reference');
        $this->addSql('DROP SEQUENCE declaration_reference_seq');
    }
}
