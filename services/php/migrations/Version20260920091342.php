<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260920091342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Corrige Template.jobImage: registro real de GitLab, no el ghcr.io que nunca se publicó';
    }

    /**
     * Migración de datos, no de esquema: los Template ya sembrados (nodejs/nestjs,
     * nodejs/nextjs) apuntaban a "ghcr.io/apperture-dev/podium-build-runner:latest",
     * una imagen que nunca se publicó ahí — 404 real contra el clúster con el
     * primer build de un repo real. .gitlab-ci.yml (build-build-runner) publica
     * de verdad en el registro de GitLab; SeedTemplatesCommand ya se corrigió,
     * pero eso solo afecta a siembras futuras — esto actualiza las filas que ya
     * existen, para que cualquier Application ya registrada (cuyo templateId no
     * cambia solo) se beneficie sin tener que re-registrarse.
     */
    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE templates SET job_image = 'registry.gitlab.com/apperturedev/podium/build-runner:latest' "
            ."WHERE job_image = 'ghcr.io/apperture-dev/podium-build-runner:latest'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE templates SET job_image = 'ghcr.io/apperture-dev/podium-build-runner:latest' "
            ."WHERE job_image = 'registry.gitlab.com/apperturedev/podium/build-runner:latest'"
        );
    }
}
