<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * User.roles (json, ex: ["ROLE_CHEF_POLE"]) -> User.role (varchar, valeur
 * unique) — un compte n'a qu'un seul rôle métier (CDC §3) ; le tableau
 * n'existait que pour satisfaire UserInterface::getRoles(): array,
 * reconstruit désormais à la volée dans User::getRoles(). Élimine au passage
 * le besoin du contournement SQL (CAST(...AS TEXT) LIKE ...) sur
 * UserRepository::findByRole()/findCadresByService() — colonne à valeur
 * unique, comparaison simple avec =.
 */
final class Version20260801043103 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'User.roles (json) -> User.role (varchar) — un seul rôle métier par compte (CDC §3).';
    }

    public function up(Schema $schema): void
    {
        // Nullable le temps de la migration de données, NOT NULL appliqué après coup.
        $this->addSql('ALTER TABLE "user" ADD role VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE \"user\" SET role = roles->>0");
        $this->addSql('ALTER TABLE "user" ALTER role SET NOT NULL');
        $this->addSql('ALTER TABLE "user" DROP roles');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD roles JSON DEFAULT NULL');
        $this->addSql("UPDATE \"user\" SET roles = json_build_array(role)");
        $this->addSql('ALTER TABLE "user" ALTER roles SET NOT NULL');
        $this->addSql('ALTER TABLE "user" DROP role');
    }
}
