<?php

declare(strict_types=1);

namespace App\Build\Domain\Port;

use App\Build\Domain\Template;
use App\Build\Domain\ValueObject\TemplateId;

interface TemplateRepository
{
    public function get(TemplateId $id): Template;

    public function findByLanguageAndFramework(string $language, string $framework): ?Template;

    public function save(Template $template): void;
}
