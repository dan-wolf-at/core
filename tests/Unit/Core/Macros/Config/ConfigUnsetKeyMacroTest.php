<?php

declare(strict_types=1);

namespace Apiato\Core\Tests\Unit\Core\Macros\Config;

use Apiato\Core\Services\Response;
use Apiato\Core\Macros\Config\UnsetKey;
use Apiato\Core\Tests\Unit\UnitTestCase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Response::class)]
final class ConfigUnsetKeyMacroTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Config::hasMacro('unset') === false) {
            Config::macro('unset', app(UnsetKey::class)());
        }
    }

    public function testItRemovesASimpleKey(): void
    {
        config()->set('foo', 'bar');

        $this->assertSame('bar', config('foo'));
        $this->assertTrue(config()->has('foo'));

        config()->unset('foo');

        $this->assertFalse(config()->has('foo'));
        $this->assertNull(config('foo'));
    }

    public function testItRemovesAKeyUsingDotNotation(): void
    {
        config()->set('services.mailgun.secret', '123');

        $this->assertSame('123', config('services.mailgun.secret'));

        config()->unset('services.mailgun.secret');

        $this->assertFalse(config()->has('services.mailgun.secret'));
        $this->assertNull(config('services.mailgun.secret'));
    }

    public function testItAcceptsAnArrayOfKeys(): void
    {
        config()->set('a', 1);
        config()->set('b', 2);

        config()->unset(['a', 'b']);

        $this->assertFalse(config()->has('a'));
        $this->assertFalse(config()->has('b'));
    }

    public function testCallingUnsetOnNonExistingKeyIsSilentlyIgnored(): void
    {
        config()->unset('ghost.key');

        $this->assertNull(config('ghost.key'));
        $this->assertFalse(config()->has('ghost.key'));
    }
}
