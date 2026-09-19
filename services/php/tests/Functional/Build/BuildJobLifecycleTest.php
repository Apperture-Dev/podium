<?php

declare(strict_types=1);

namespace Tests\Functional\Build;

use App\AppManager\Domain\Event\ApplicationBuildRequested;
use App\Build\Application\EventHandler\ApplicationBuildRequestedHandler;
use App\Build\Application\EventHandler\JobFailedHandler;
use App\Build\Application\EventHandler\JobSucceededHandler;
use App\Build\Application\Message\JobFailed;
use App\Build\Application\Message\JobSucceeded;
use App\Build\Domain\BuildStatus;
use App\Build\Domain\Event\BuildFailed;
use App\Build\Domain\Event\BuildJobRequested;
use App\Build\Domain\Event\BuildSucceeded;
use App\Build\Domain\Port\BuildJobRepository;
use App\Build\Domain\Port\TemplateRepository;
use App\Build\Domain\Template;
use App\Build\Domain\ValueObject\BuildJobId;
use App\Project\Domain\Port\ProjectRepository;
use App\Project\Domain\Project;
use App\Project\Domain\ValueObject\ProjectName;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class BuildJobLifecycleTest extends KernelTestCase
{
    private ApplicationBuildRequestedHandler $applicationBuildRequestedHandler;
    private JobSucceededHandler $jobSucceededHandler;
    private JobFailedHandler $jobFailedHandler;
    private BuildJobRepository $buildJobs;
    private string $projectId;
    private string $templateId;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();
        $this->applicationBuildRequestedHandler = $container->get(ApplicationBuildRequestedHandler::class);
        $this->jobSucceededHandler = $container->get(JobSucceededHandler::class);
        $this->jobFailedHandler = $container->get(JobFailedHandler::class);
        $this->buildJobs = $container->get(BuildJobRepository::class);

        $projects = $container->get(ProjectRepository::class);
        $project = Project::register('https://github.com/team/repo', 'team-1', ProjectName::fromString('Test Project'));
        $projects->save($project);
        $this->projectId = $project->id()->toString();

        $templates = $container->get(TemplateRepository::class);
        $template = Template::define('node', 'express', 'ghcr.io/podium/buildah-node:latest', []);
        $templates->save($template);
        $this->templateId = $template->id()->toString();
    }

    public function testBuildSucceedsAndPublishesBuildJobRequestedThenBuildSucceeded(): void
    {
        ($this->applicationBuildRequestedHandler)(new ApplicationBuildRequested('backend', $this->projectId, $this->templateId, 'v1', 'rev-1', 'https://github.com/team/repo', 'github'));

        $requested = $this->envelopesOn('build_job_requested');
        self::assertCount(1, $requested);
        self::assertInstanceOf(BuildJobRequested::class, $requested[0]->getMessage());
        $buildJobId = $requested[0]->getMessage()->buildJobId;

        ($this->jobSucceededHandler)(new JobSucceeded($buildJobId, 'registry/backend:rev-1', [], ['API_URL' => 'https://x'], []));

        $buildJob = $this->buildJobs->get(BuildJobId::fromString($buildJobId));
        self::assertSame(BuildStatus::Succeeded, $buildJob->status());

        $succeeded = $this->envelopesOn('build_succeeded');
        self::assertCount(1, $succeeded);
        self::assertInstanceOf(BuildSucceeded::class, $succeeded[0]->getMessage());
        self::assertSame('registry/backend:rev-1', $succeeded[0]->getMessage()->image);
    }

    public function testBuildFailsAndPublishesBuildFailed(): void
    {
        ($this->applicationBuildRequestedHandler)(new ApplicationBuildRequested('backend', $this->projectId, $this->templateId, 'v1', 'rev-1', 'https://github.com/team/repo', 'github'));

        $requested = $this->envelopesOn('build_job_requested');
        $buildJobId = $requested[0]->getMessage()->buildJobId;

        ($this->jobFailedHandler)(new JobFailed($buildJobId, 'compile error'));

        $buildJob = $this->buildJobs->get(BuildJobId::fromString($buildJobId));
        self::assertSame(BuildStatus::Failed, $buildJob->status());

        $failed = $this->envelopesOn('build_failed_app_manager');
        self::assertCount(1, $failed);
        self::assertInstanceOf(BuildFailed::class, $failed[0]->getMessage());
        self::assertSame('compile error', $failed[0]->getMessage()->errorMessage);
    }

    /** @return list<\Symfony\Component\Messenger\Envelope> */
    private function envelopesOn(string $transportName): array
    {
        $transport = self::getContainer()->get('messenger.transport.'.$transportName);
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport->getSent();
    }
}
