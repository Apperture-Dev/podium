<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260919141759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE applications (service_name VARCHAR(255) NOT NULL, project_id VARCHAR(36) NOT NULL, team_id VARCHAR(36) NOT NULL, template_id VARCHAR(36) NOT NULL, state VARCHAR(20) NOT NULL, version VARCHAR(255) NOT NULL, has_pending_source_change BOOLEAN NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_application_project_service ON applications (project_id, service_name)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE applications');
    }
}
