<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260919162833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // backfill seguro para filas ya existentes antes de exigir NOT NULL
        $this->addSql("ALTER TABLE projects ADD name VARCHAR(150) DEFAULT 'Untitled Project' NOT NULL");
        $this->addSql('ALTER TABLE projects ALTER name DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE projects DROP name');
    }
}
