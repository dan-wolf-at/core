<?php

declare(strict_types=1);

namespace Apiato\Core\Tests\Unit\Abstracts\Tests\PhpUnit;

use Apiato\Core\Abstracts\Tests\PhpUnit\TestCase;
use Apiato\Core\Tests\Unit\UnitTestCase;
use Apiato\Core\Traits\HashIdTrait;
use Apiato\Core\Traits\TestCaseTrait;
use Apiato\Core\Traits\TestTraits\PhpUnit\TestAssertionHelperTrait;
use Apiato\Core\Traits\TestTraits\PhpUnit\TestAuthHelperTrait;
use Apiato\Core\Traits\TestTraits\PhpUnit\TestRequestHelperTrait;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TestCase::class)]
final class TestCaseTest extends UnitTestCase
{
    public function testUsesTraits(): void
    {
        $traits = [
            HashIdTrait::class,
            LazilyRefreshDatabase::class,
            TestAssertionHelperTrait::class,
            TestAuthHelperTrait::class,
            TestCaseTrait::class,
            TestRequestHelperTrait::class,
            WithFaker::class,
        ];

        foreach ($traits as $trait) {
            self::assertContains($trait, class_uses_recursive(TestCase::class));
        }
    }
}
