<?php

declare(strict_types=1);

namespace Apiato\Core\Tests\Unit\Traits;

use Apiato\Core\Abstracts\Transformers\Transformer;
use Apiato\Core\Tests\Infrastructure\Doubles\User;
use Apiato\Core\Tests\Infrastructure\Doubles\UserFactory;
use Apiato\Core\Tests\Infrastructure\Doubles\UserTransformer;
use Apiato\Core\Tests\Unit\UnitTestCase;
use Apiato\Core\Traits\ResponseTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ResponseTrait::class)]
final class ResponseTraitTest extends UnitTestCase
{
    private object $trait;

    private User $user;

    private Transformer $transformer;

    private array $customMetadata;

    private array $metadata;

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        $this->trait = new class {
            use ResponseTrait;
        };

        $this->user = UserFactory::new()->withParent()->createOne();
        $this->transformer = new UserTransformer();
        $this->customMetadata = [
            'key' => 'value',
        ];
        $this->metadata = [
            'something' => $this->customMetadata,
        ];
    }

    public function testTransform(): void
    {
        $result = $this->trait
            ->withMeta($this->metadata)
            ->transform(
                data: $this->user,
                transformerName: $this->transformer,
                meta: $this->customMetadata,
            );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('object', $result['data']);
        $this->assertEquals($this->user->getResourceKey(), $result['data']['object']);
        $this->assertArrayNotHasKey('parent', $result['data']);
        $this->assertMetadata($result);
    }

    public function testCanInclude(): void
    {
        $include = 'parent';

        $result = $this->trait
            ->withMeta($this->metadata)
            ->transform(
                data: $this->user,
                transformerName: $this->transformer,
                includes: [$include],
                meta: $this->customMetadata,
            );

        $this->assertArrayHasKey('parent', $result['data']);
        $this->assertNotNull($result['data']['parent']);
        $this->assertMetadata($result);
        $this->assertContains($include, $result['meta']['include']);
    }

    public static function resourceKeyProvider(): \Iterator
    {
        yield 'null' => [
            null,
            'User',
        ];
        yield 'false' => [
            false,
            'User',
        ];
        yield 'empty string' => [
            '',
            'User',
        ];
        yield 'empty array' => [
            [],
            'User',
        ];
    }

    #[DataProvider('resourceKeyProvider')]
    public function testCanOverrideResourceKey(null|bool|string|array $resourceKey, string $expected): void
    {
        $result = $this->trait
            ->withMeta($this->metadata)
            ->transform(
                data: $this->user,
                transformerName: $this->transformer,
                meta: $this->customMetadata,
                resourceKey: $resourceKey,
            );

        $this->assertEquals($expected, $result['data']['object']);
    }

    private function assertMetadata(array $result): void
    {
        $this->assertArrayHasKey('meta', $result);
        foreach ($this->metadata as $key => $value) {
            $this->assertArrayHasKey($key, $result['meta']);
            $this->assertEquals($value, $result['meta'][$key]);
        }

        $this->assertArrayHasKey('include', $result['meta']);
        $this->assertArrayHasKey('custom', $result['meta']);
        foreach ($this->customMetadata as $key => $value) {
            $this->assertArrayHasKey($key, $result['meta']['custom']);
            $this->assertEquals($value, $result['meta']['custom'][$key]);
        }
    }
}
