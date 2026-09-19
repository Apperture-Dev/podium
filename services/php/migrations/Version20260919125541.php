<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260919125541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE projects (hash VARCHAR(8) NOT NULL, team_id VARCHAR(36) NOT NULL, repository_url VARCHAR(255) NOT NULL, known_service_names JSON NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5C93B3A4D1B862B8 ON projects (hash)');
        $this->addSql('CREATE TABLE teams (name VARCHAR(150) NOT NULL, members JSON NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE projects');
        $this->addSql('DROP TABLE teams');
    }
}
