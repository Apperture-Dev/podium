<?php

declare(strict_types=1);

namespace App\Build\Application;

use App\Build\Domain\BuildJob;
use App\Build\Domain\Port\BuildJobRepository;
use App\Build\Domain\Port\TemplateRepository;
use App\Build\Domain\Template;
use App\Build\Domain\ValueObject\BuildJobId;
use App\Build\Domain\ValueObject\ParamField;
use App\Build\Domain\ValueObject\TemplateId;
use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\ValueObject\ProjectId;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class ApplicationService
{
    public function __construct(
        private TemplateRepository $templates,
        private BuildJobRepository $buildJobs,
        private ProjectRepository $projects,
        private MessageBusInterface $eventBus,
    ) {
    }

    /** @param array<string, ParamField> $paramSchema */
    public function defineTemplate(string $language, string $framework, string $jobImage, int $defaultPort, array $paramSchema): string
    {
        $template = Template::define($language, $framework, $jobImage, $defaultPort, $paramSchema);
        $this->templates->save($template);

        return $template->id()->toString();
    }

    /** Reacciona a `ApplicationBuildRequested` (publicado por App Manager). */
    public function startBuildJob(string $serviceName, string $projectId, string $templateId, string $version, string $revision, string $repositoryUrl, string $provider): void
    {
        $project = $this->projects->get(ProjectId::fromString($projectId));
        $template = $this->templates->get(TemplateId::fromString($templateId));

        $buildJob = BuildJob::request(
            $project->teamId(),
            $serviceName,
            $projectId,
            $templateId,
            $template->jobImage(),
            $version,
            $revision,
            $repositoryUrl,
            $provider,
        );
        $events = $buildJob->releaseEvents();

        $this->buildJobs->save($buildJob);
        $this->dispatchAll($events);
    }

    /** Reacciona a `JobSucceeded` (lanzador de Kubernetes, Go, diferido). */
    public function completeBuildJob(string $buildJobId, string $image, array $buildEnvVars, array $deployEnvVars, array $databaseDeclaration): void
    {
        $buildJob = $this->buildJobs->get(BuildJobId::fromString($buildJobId));
        $template = $this->templates->get(TemplateId::fromString($buildJob->templateId()));

        $events = $buildJob->completeBuildJob($image, $template->defaultPort(), $buildEnvVars, $deployEnvVars, $databaseDeclaration);

        $this->buildJobs->save($buildJob);
        $this->dispatchAll($events);
    }

    /** Reacciona a `JobFailed` (lanzador de Kubernetes, Go, diferido). */
    public function failBuildJob(string $buildJobId, string $errorMessage): void
    {
        $buildJob = $this->buildJobs->get(BuildJobId::fromString($buildJobId));

        $events = $buildJob->failBuildJob($errorMessage);

        $this->buildJobs->save($buildJob);
        $this->dispatchAll($events);
    }

    /** @param list<object> $events */
    private function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
