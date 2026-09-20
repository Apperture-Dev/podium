<?php

declare(strict_types=1);

namespace Tests\Functional\Build;

use App\Build\Domain\Port\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class SeedTemplatesCommandTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private TemplateRepository $templates;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $this->commandTester = new CommandTester($application->find('app:build:seed-templates'));
        $this->templates = static::getContainer()->get(TemplateRepository::class);
    }

    public function testSeedsTheNodejsNestjsTemplate(): void
    {
        $this->commandTester->execute([]);

        $this->commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('nodejs/nestjs', $this->commandTester->getDisplay());
    }

    public function testSeedsThePythonFastapiTemplateOnThePortUvicornListensOn(): void
    {
        $this->commandTester->execute([]);

        $template = $this->templates->findByLanguageAndFramework('python', 'fastapi');

        self::assertNotNull($template);
        self::assertSame(8000, $template->defaultPort());
    }

    public function testSeedsTheSpaTemplatesOnThePortNginxListensOn(): void
    {
        $this->commandTester->execute([]);

        foreach (['react', 'vue'] as $framework) {
            $template = $this->templates->findByLanguageAndFramework('nodejs', $framework);

            self::assertNotNull($template, \sprintf('No se sembró la plantilla nodejs/%s', $framework));
            self::assertSame(80, $template->defaultPort(), \sprintf('nodejs/%s sirve estáticos con nginx', $framework));
        }
    }

    /**
     * Sembrar una plantilla nueva obliga a re-ejecutar el comando allí donde
     * las anteriores ya existen. Si eso redefiniera las existentes, el
     * catálogo acabaría con dos filas por lenguaje/framework y
     * `findByLanguageAndFramework` (un `findOneBy`) devolvería cualquiera
     * de las dos.
     */
    public function testRunningItTwiceKeepsTheTemplateAlreadyDefined(): void
    {
        $this->commandTester->execute([]);
        $this->commandTester->execute([]);

        self::assertSame(1, $this->countTemplatesFor('nodejs', 'nestjs'));
    }

    /**
     * Se cuenta contra la base de datos en vez de comparar el id que
     * devuelve `findByLanguageAndFramework`: ese es un `findOneBy`, así que
     * con dos filas duplicadas seguiría devolviendo una sin fallar.
     */
    private function countTemplatesFor(string $language, string $framework): int
    {
        return (int) static::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('SELECT COUNT(t.id) FROM App\Build\Domain\Template t WHERE t.language = :language AND t.framework = :framework')
            ->setParameter('language', $language)
            ->setParameter('framework', $framework)
            ->getSingleScalarResult();
    }
}
