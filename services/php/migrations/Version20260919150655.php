<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260919150655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE build_jobs (team_id VARCHAR(36) NOT NULL, service_name VARCHAR(255) NOT NULL, project_id VARCHAR(36) NOT NULL, template_id VARCHAR(36) NOT NULL, version VARCHAR(255) NOT NULL, commit_id VARCHAR(255) NOT NULL, repository_url VARCHAR(255) NOT NULL, provider VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, image VARCHAR(255) DEFAULT NULL, yaml_snapshot JSON DEFAULT NULL, error_message TEXT DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE templates (language VARCHAR(255) NOT NULL, framework VARCHAR(255) NOT NULL, job_image VARCHAR(255) NOT NULL, param_schema JSON NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE build_jobs');
        $this->addSql('DROP TABLE templates');
    }
}
