<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Base class for unit tests.
 *
 * Unit tests should:
 * - Test a single class in isolation (aggregates, Value Objects)
 * - Not boot the kernel, not access database, filesystem or network
 */
abstract class UnitTestCase extends TestCase
{
}
