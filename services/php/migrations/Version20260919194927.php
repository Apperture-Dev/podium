<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260919194927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Backfill existing rows (framework unknown, created_at = now) before enforcing NOT NULL.
        $this->addSql("ALTER TABLE applications ADD framework VARCHAR(255) DEFAULT '' NOT NULL");
        $this->addSql('ALTER TABLE applications ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW() NOT NULL');
        $this->addSql('ALTER TABLE projects ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NOW() NOT NULL');
        $this->addSql('ALTER TABLE applications ALTER COLUMN framework DROP DEFAULT');
        $this->addSql('ALTER TABLE applications ALTER COLUMN created_at DROP DEFAULT');
        $this->addSql('ALTER TABLE projects ALTER COLUMN created_at DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE applications DROP framework');
        $this->addSql('ALTER TABLE applications DROP created_at');
        $this->addSql('ALTER TABLE projects DROP created_at');
    }
}
