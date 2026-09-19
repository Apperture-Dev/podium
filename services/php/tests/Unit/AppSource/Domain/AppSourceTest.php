<?php

declare(strict_types=1);

namespace Tests\Unit\AppSource\Domain;

use App\AppSource\Domain\AppSource;
use App\AppSource\Domain\Event\SourceChanged;
use Tests\Unit\UnitTestCase;

final class AppSourceTest extends UnitTestCase
{
    private AppSource $appSource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appSource = AppSource::register('project-1', 'https://github.com/team/repo', 'github');
    }

    public function testRegisterStartsWithoutAKnownRevision(): void
    {
        self::assertNull($this->appSource->revision());
        self::assertSame('project-1', $this->appSource->projectId());
        self::assertSame('https://github.com/team/repo', $this->appSource->repositoryUrl());
        self::assertSame('github', $this->appSource->provider());
    }

    public function testFirstRevisionProducesSourceChanged(): void
    {
        $events = $this->appSource->recordRevision('rev-1');

        self::assertCount(1, $events);
        self::assertInstanceOf(SourceChanged::class, $events[0]);
        self::assertSame('rev-1', $events[0]->revision);
        self::assertSame('rev-1', $this->appSource->revision());
    }

    public function testSameRevisionProducesNothing(): void
    {
        $this->appSource->recordRevision('rev-1');

        $events = $this->appSource->recordRevision('rev-1');

        self::assertSame([], $events);
    }

    public function testDifferentRevisionProducesSourceChangedAgain(): void
    {
        $this->appSource->recordRevision('rev-1');

        $events = $this->appSource->recordRevision('rev-2');

        self::assertCount(1, $events);
        self::assertSame('rev-2', $events[0]->revision);
    }
}
