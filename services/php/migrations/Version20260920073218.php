<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260920073218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añade Template.defaultPort — convención sobre configuración por lenguaje/framework, igual que jobImage';
    }

    public function up(Schema $schema): void
    {
        // Backfill los Template existentes (hoy solo Node/3000) antes de forzar NOT NULL.
        $this->addSql('ALTER TABLE templates ADD default_port INT DEFAULT 3000 NOT NULL');
        $this->addSql('ALTER TABLE templates ALTER COLUMN default_port DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE templates DROP default_port');
    }
}
