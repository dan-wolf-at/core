<?php

declare(strict_types=1);

namespace Apiato\Core\Tests\Unit\Core\Macros\Config;

use Apiato\Core\Macros\Config\UnsetKey;
use Apiato\Core\Services\Response;
use Apiato\Core\Tests\Unit\UnitTestCase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Response::class)]
final class ConfigUnsetKeyMacroTest extends UnitTestCase
{
    public function testItRemovesASimpleKey(): void
    {
        config()->set('foo', 'bar');

        self::assertSame('bar', config('foo'));
        self::assertTrue(config()->has('foo'));

        config()->unset('foo');

        self::assertFalse(config()->has('foo'));
        self::assertNull(config('foo'));
    }

    public function testItRemovesAKeyUsingDotNotation(): void
    {
        config()->set('services.mailgun.secret', '123');

        self::assertSame('123', config('services.mailgun.secret'));

        config()->unset('services.mailgun.secret');

        self::assertFalse(config()->has('services.mailgun.secret'));
        self::assertNull(config('services.mailgun.secret'));
    }

    public function testItAcceptsAnArrayOfKeys(): void
    {
        config()->set('a', 1);
        config()->set('b', 2);

        config()->unset(['a', 'b']);

        self::assertFalse(config()->has('a'));
        self::assertFalse(config()->has('b'));
    }

    public function testCallingUnsetOnNonExistingKeyIsSilentlyIgnored(): void
    {
        config()->unset('ghost.key');

        self::assertNull(config('ghost.key'));
        self::assertFalse(config()->has('ghost.key'));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (Config::hasMacro('unset') === false) {
            Config::macro('unset', app(UnsetKey::class)());
        }
    }
}
