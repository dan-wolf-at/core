<?php

declare(strict_types=1);

namespace Apiato\Core\Tests\Unit\Services;

use Apiato\Core\Services\Response;
use Apiato\Core\Tests\Infrastructure\Doubles\BookFactory;
use Apiato\Core\Tests\Infrastructure\Doubles\User;
use Apiato\Core\Tests\Infrastructure\Doubles\UserFactory;
use Apiato\Core\Tests\Infrastructure\Doubles\UserRepository;
use Apiato\Core\Tests\Infrastructure\Doubles\UserTransformer;
use Apiato\Core\Tests\Unit\UnitTestCase;
use Illuminate\Testing\Fluent\AssertableJson;
use League\Fractal\ParamBag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Response::class)]
final class ResponseTest extends UnitTestCase
{
    private const string FIELDSET_KEY = 'fields';

    private User $user;

    public static function csvIncludeDataProvider(): \Iterator
    {
        yield 'single string' => [
            'parent',
            ['data.parent'],
        ];
        yield 'single string nested' => [
            'children.books',
            ['data.children.data.0.books'],
        ];
        yield 'csv string' => [
            'parent,children',
            ['data.parent', 'data.children'],
        ];
        yield 'csv string and nested' => [
            'parent,children.books',
            ['data.parent', 'data.children.data.0.books'],
        ];
    }

    public static function arrayIncludeDataProvider(): \Iterator
    {
        yield 'single array' => [
            ['parent'],
            ['data.parent'],
        ];
        yield 'multiple array' => [
            ['parent', 'children'],
            ['data.parent', 'data.children'],
        ];
        yield 'multiple array nested' => [
            ['parent.books', 'children'],
            ['data.parent.data.books', 'data.children'],
        ];
    }

    public static function paginatedIncludeMetaDataDataProvider(): \Iterator
    {
        yield 'single string' => [
            'parent',
        ];
        yield 'single string nested' => [
            'children.books',
        ];
        yield 'csv string' => [
            'parent,children',
        ];
        yield 'csv string and nested' => [
            'parent,children.books',
        ];
        yield 'single array' => [
            ['parent'],
        ];
        yield 'multiple array' => [
            ['parent', 'children'],
        ];
        yield 'multiple array nested' => [
            ['parent.books', 'children'],
        ];
    }

    public static function csvExcludeDataProvider(): \Iterator
    {
        yield 'single string' => [
            'parent',
            ['data.parent'],
        ];
        yield 'single string nested' => [
            'children.books',
            ['data.children.data.0.books'],
        ];
        yield 'csv string' => [
            'parent,children',
            ['data.parent', 'data.children'],
        ];
        yield 'csv string and nested' => [
            'parent,children.books',
            ['data.parent', 'data.children.data.0.books'],
        ];
    }

    public static function arrayExcludeDataProvider(): \Iterator
    {
        yield 'single array' => [
            ['parent'],
            ['data.parent'],
        ];
        yield 'multiple array' => [
            ['parent', 'children'],
            ['data.parent', 'data.children'],
        ];
        yield 'multiple array nested' => [
            ['parent.books', 'children'],
            ['data.parent.data.books', 'data.children'],
        ];
    }

    public static function paginatedExcludeMetaDataDataProvider(): \Iterator
    {
        yield 'single string' => [
            'parent',
        ];
        yield 'single string nested' => [
            'children.books',
        ];
        yield 'csv string' => [
            'parent,children',
        ];
        yield 'csv string and nested' => [
            'parent,children.books',
        ];
        yield 'single array' => [
            ['parent'],
        ];
        yield 'multiple array' => [
            ['parent', 'children'],
        ];
        yield 'multiple array nested' => [
            ['parent.books', 'children'],
        ];
    }

    public static function validResourceNameProvider(): \Iterator
    {
        yield 'empty string' => [
            '',
        ];
        yield 'string' => [
            'wat',
        ];
    }

    public static function invalidResourceNameProvider(): \Iterator
    {
        yield 'null' => [
            null,
        ];
        yield 'false' => [
            false,
        ];
    }

    public static function fieldsetDataProvider(): \Iterator
    {
        yield 'without includes' => [
            ['User' => 'id,email'],
            ['data.id', 'data.email'],
            ['data.object', 'data.name', 'data.created_at', 'data.updated_at', 'data.children', 'data.books'],
        ];
        yield 'only filter nested include keys' => [
            ['Book' => 'author,title'],
            ['data.object', 'data.id', 'data.email', 'data.name', 'data.created_at', 'data.updated_at', 'data.books.data.0.author', 'data.books.data.0.title'],
            ['data.books.data.0.id', 'data.books.data.0.created_at', 'data.books.data.0.updated_at'],
        ];
        yield 'with first level includes - no filter' => [
            ['User' => 'object,id,email,books'],
            ['data.object', 'data.id', 'data.email', 'data.books.data.0.object', 'data.books.data.0.id', 'data.books.data.0.title', 'data.books.data.0.author', 'data.books.data.0.created_at', 'data.books.data.0.updated_at'],
            ['data.name', 'data.created_at', 'data.updated_at'],
        ];
        yield 'with first level includes - filter' => [
            ['User' => 'object,id,email,books', 'Book' => 'object,author'],
            ['data.object', 'data.id', 'data.email', 'data.books.data.0.object', 'data.books.data.0.author'],
            ['data.children', 'data.books.data.0.id', 'data.books.data.0.title', 'data.books.data.0.created_at', 'data.books.data.0.updated_at', 'data.name', 'data.created_at', 'data.updated_at'],
        ];
        yield 'with nested includes - no filter' => [
            ['User' => 'object,id,email,children,books'],
            ['data.object', 'data.id', 'data.email', 'data.children.data.0.object', 'data.children.data.0.id', 'data.children.data.0.email', 'data.children.data.0.books.data.0.object', 'data.children.data.0.books.data.0.id', 'data.children.data.0.books.data.0.title', 'data.children.data.0.books.data.0.author', 'data.children.data.0.books.data.0.created_at', 'data.children.data.0.books.data.0.updated_at'],
            ['data.name', 'data.created_at', 'data.updated_at'],
        ];
        yield 'with nested includes - filter' => [
            ['User' => 'id,email,children,books', 'Book' => 'id'],
            ['data.id', 'data.email', 'data.children.data.0.id', 'data.children.data.0.email', 'data.children.data.0.books.data.0.id'],
            ['data.object', 'data.children.data.0.object', 'data.children.data.0.books.data.0.object', 'data.children.data.0.books.data.0.title', 'data.children.data.0.books.data.0.author', 'data.children.data.0.books.data.0.created_at', 'data.children.data.0.books.data.0.updated_at', 'data.name', 'data.created_at', 'data.updated_at'],
        ];
    }

    #[DataProvider('csvIncludeDataProvider')]
    public function testSingleResourceCanHandleCSVInclude(string $include, array $expected): void
    {
        request()->merge(['include' => $include]);
        $response = Response::create($this->user);
        $response->transformWith(UserTransformer::class);

        $assertableJson = AssertableJson::fromArray($response->toArray());

        foreach ($expected as $expectation) {
            $assertableJson->has($expectation);
        }
    }

    #[DataProvider('arrayIncludeDataProvider')]
    public function testSingleResourceCanHandleArrayInclude(array $include, array $expected): void
    {
        request()->merge(['include' => $include]);
        $response = Response::create($this->user);
        $response->transformWith(UserTransformer::class);

        $assertableJson = AssertableJson::fromArray($response->toArray());

        foreach ($expected as $expectation) {
            $assertableJson->has($expectation);
        }
    }

    #[DataProvider('paginatedIncludeMetaDataDataProvider')]
    public function testPaginatedResourceMetaDataAndInclude(string|array $include): void
    {
        request()->merge(['include' => $include]);
        UserFactory::new()->count(3)->create();
        $users = app(UserRepository::class, ['app' => $this->app])->paginate();
        $response = Response::create($users)->transformWith(UserTransformer::class);

        $assertableJson = AssertableJson::fromArray($response->toArray());

        $assertableJson->has('meta.include', fn (AssertableJson $json): AssertableJson => $json->whereAll(['parent', 'children', 'books']));
    }

    #[DataProvider('fieldsetDataProvider')]
    public function testCanFilterResponse(array $fields, array $expected, array $missing): void
    {
        request()->merge(['include' => 'books,children.books', self::FIELDSET_KEY => $fields]);
        $response = Response::create($this->user);
        $response->transformWith(UserTransformer::class);

        $assertableJson = AssertableJson::fromArray($response->toArray());

        foreach ($expected as $expectation) {
            $assertableJson->has($expectation);
            $assertableJson->has('meta.include', fn (AssertableJson $json): AssertableJson => $json->whereAll(['parent', 'children', 'books']));
        }

        foreach ($missing as $expectation) {
            $assertableJson->missing($expectation);
        }
    }

    #[DataProvider('csvExcludeDataProvider')]
    public function testSingleResourceCanHandleCSVExclude(string $exclude, array $expected): void
    {
        request()->merge(['exclude' => $exclude]);
        $response = Response::create($this->user);
        $response->transformWith(UserTransformer::class)->parseIncludes($exclude);

        $assertableJson = AssertableJson::fromArray($response->toArray());

        foreach ($expected as $expectation) {
            $assertableJson->missing($expectation);
        }
    }

    #[DataProvider('arrayExcludeDataProvider')]
    public function testSingleResourceCanHandleArrayExclude(array $exclude, array $expected): void
    {
        request()->merge(['exclude' => $exclude]);
        $response = Response::create($this->user);
        $response->transformWith(UserTransformer::class)->parseIncludes($exclude);

        $assertableJson = AssertableJson::fromArray($response->toArray());

        foreach ($expected as $expectation) {
            $assertableJson->missing($expectation);
        }
    }

    #[DataProvider('paginatedExcludeMetaDataDataProvider')]
    public function testPaginatedResourceMetaDataAndExclude(string|array $exclude): void
    {
        request()->merge(['exclude' => $exclude]);
        UserFactory::new()->count(3)->create();
        $users = app(UserRepository::class, ['app' => $this->app])->paginate();
        $response = Response::create($users)->transformWith(UserTransformer::class)->parseIncludes($exclude);

        $assertableJson = AssertableJson::fromArray($response->toArray());

        $assertableJson->has('meta.include', fn (AssertableJson $json): AssertableJson => $json->whereAll(['parent', 'children', 'books']));
    }

    #[DataProvider('validResourceNameProvider')]
    public function testCanOverrideMainResourceName(string $resourceName): void
    {
        request()->merge(['include' => 'books,children.books', self::FIELDSET_KEY => [$resourceName => 'id', 'Book' => 'author,title']]);
        $response = Response::create($this->user);
        $response->transformWith(UserTransformer::class);
        $response->withResourceName($resourceName);

        $assertableJson = AssertableJson::fromArray($response->toArray());

        $assertableJson->missing('data.object');
    }

    #[DataProvider('invalidResourceNameProvider')]
    public function testGivenInvalidNameProvidedRevertToDefaultMainResourceName(?bool $resourceName): void
    {
        request()->merge(['include' => 'books,children.books', self::FIELDSET_KEY => [$resourceName => 'id', 'Book' => 'author,title']]);
        $response = Response::create($this->user);
        $response->transformWith(UserTransformer::class);
        $response->withResourceName($resourceName);

        $assertableJson = AssertableJson::fromArray($response->toArray());

        $assertableJson->has('data.object');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('fractal.auto_fieldsets.enabled', true);
        config()->set('fractal.auto_fieldsets.request_key', self::FIELDSET_KEY);

        $this->user = UserFactory::new()
            ->for(UserFactory::new()->has(BookFactory::new()), 'parent')
            ->has(UserFactory::new()->has(BookFactory::new())->count(2), 'children')
            ->has(BookFactory::new()->count(2))
            ->createOne();
    }

    public function testCanGenerate200OKResponse(): void
    {
        $response = Response::create($this->user);
        $response->transformWith(UserTransformer::class);

        $jsonResponse = $response->ok();

        $this->assertEquals(\Symfony\Component\HttpFoundation\Response::HTTP_OK, $jsonResponse->getStatusCode());
    }

    public function testCanGenerate202OAcceptedResponse(): void
    {
        $response = Response::create($this->user);
        $response->transformWith(UserTransformer::class);

        $jsonResponse = $response->accepted();

        $this->assertEquals(\Symfony\Component\HttpFoundation\Response::HTTP_ACCEPTED, $jsonResponse->getStatusCode());
    }

    public function testCanGenerate201CreatedResponse(): void
    {
        $response = Response::create($this->user);
        $response->transformWith(UserTransformer::class);

        $jsonResponse = $response->created();

        $this->assertEquals(\Symfony\Component\HttpFoundation\Response::HTTP_CREATED, $jsonResponse->getStatusCode());
    }

    public function testCanGenerate204NoContentResponse(): void
    {
        $response = Response::create($this->user);
        $response->transformWith(UserTransformer::class);

        $jsonResponse = $response->noContent();

        $this->assertEquals(\Symfony\Component\HttpFoundation\Response::HTTP_NO_CONTENT, $jsonResponse->getStatusCode());
    }

    public function testCanGetRequestedIncludes(): void
    {
        request()->merge(['include' => 'books,children.books']);

        $result = Response::getRequestedIncludes();

        $this->assertSame(['books', 'children', 'children.books'], $result);
    }

    public function testCanProcessIncludeParamsWithResourceName(): void
    {
        $include = 'books';
        $includeWithParams = $include . ':test(2|value)';
        request()->merge(['include' => $includeWithParams]);
        $response = Response::create($this->user);
        $response->transformWith(UserTransformer::class);
        $response->withResourceName('User');

        $response->respond();

        $scope = $response->getTransformer()?->getCurrentScope();
        $identifier = $scope?->getIdentifier($include);
        $actualParams = $scope?->getManager()->getIncludeParams($identifier);
        $paramBag = new ParamBag([
            'test' => ['2', 'value'],
        ]);
        $this->assertEquals($paramBag, $actualParams);
    }
}
