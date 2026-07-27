<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727041825 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE suggestion_rmm DROP CONSTRAINT fk_f5745fecc06258a3');
        $this->addSql('DROP TABLE suggestion_rmm');
        $this->addSql('ALTER TABLE declaration DROP suggestion_rmm');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE suggestion_rmm (id UUID NOT NULL, ordre_jour TEXT NOT NULL, is_auto BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, declaration_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_f5745fecc06258a3 ON suggestion_rmm (declaration_id)');
        $this->addSql('ALTER TABLE suggestion_rmm ADD CONSTRAINT fk_f5745fecc06258a3 FOREIGN KEY (declaration_id) REFERENCES declaration (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE declaration ADD suggestion_rmm BOOLEAN NOT NULL');
    }
}
