<?php

declare(strict_types=1);

namespace Apiato\Core\Tests\Unit\Traits;

use Apiato\Core\Abstracts\Repositories\Repository;
use Apiato\Core\Exceptions\CoreInternalErrorException;
use Apiato\Core\Tests\Unit\UnitTestCase;
use Apiato\Core\Traits\HasRequestCriteriaTrait;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Prettus\Repository\Criteria\RequestCriteria;
use Symfony\Component\HttpFoundation\InputBag;

#[CoversClass(HasRequestCriteriaTrait::class)]
final class HasRequestCriteriaTraitTest extends UnitTestCase
{
    private string $searchKey;

    private Repository|MockInterface $mockRepository;

    private object $traitObject;

    public function testAddRequestCriteriaPushesCriteriaToRepository(): void
    {
        $this->mockRepository
            ->shouldReceive('pushCriteria')
            ->once()
            ->with(Mockery::type(RequestCriteria::class))
            ->andReturnSelf();

        $result = $this->traitObject->addRequestCriteria($this->mockRepository);

        self::assertSame($this->traitObject, $result);
    }

    public function testAddRequestCriteriaUsesInternalRepositoryIfNoneProvided(): void
    {
        $this->mockRepository
            ->shouldReceive('pushCriteria')
            ->once()
            ->with(Mockery::type(RequestCriteria::class))
            ->andReturnSelf();

        $result = $this->traitObject->addRequestCriteria();

        self::assertSame($this->traitObject, $result);
    }

    public function testAddRequestCriteriaThrowsExceptionIfNoRepositoryAvailable(): void
    {
        $instanceWithoutRepo = new class () {
            use HasRequestCriteriaTrait;
        };

        $this->expectException(CoreInternalErrorException::class);
        $this->expectExceptionMessage('No protected or public accessible repository available');

        $instanceWithoutRepo->addRequestCriteria();
    }

    public function testRemoveRequestCriteriaPopsCriteriaFromRepository(): void
    {
        $this->mockRepository
            ->shouldReceive('popCriteria')
            ->once()
            ->with(RequestCriteria::class)
            ->andReturnSelf();

        $result = $this->traitObject->removeRequestCriteria($this->mockRepository);

        self::assertSame($this->traitObject, $result);
    }

    public function testRemoveRequestCriteriaUsesInternalRepositoryIfNoneProvided(): void
    {
        $this->mockRepository
            ->shouldReceive('popCriteria')
            ->once()
            ->with(RequestCriteria::class)
            ->andReturnSelf();

        $result = $this->traitObject->removeRequestCriteria();

        self::assertSame($this->traitObject, $result);
    }

    public function testRemoveRequestCriteriaThrowsExceptionIfNoRepositoryAvailable(): void
    {
        $instanceWithoutRepo = new class () {
            use HasRequestCriteriaTrait;
        };

        $this->expectException(CoreInternalErrorException::class);
        $this->expectExceptionMessage('No protected or public accessible repository available');

        $instanceWithoutRepo->removeRequestCriteria();
    }

    public function testValidateRepositoryReturnsProvidedRepository(): void
    {
        $result = $this->traitObject->exposeValidateRepository($this->mockRepository);

        self::assertSame($this->mockRepository, $result);
    }

    public function testValidateRepositoryReturnsRepositoryFromInstance(): void
    {
        $result = $this->traitObject->exposeValidateRepository();

        self::assertSame($this->mockRepository, $result);
    }

    public function testValidateRepositoryThrowsExceptionForNullRepository(): void
    {
        $nullRepoInstance = new class () {
            use HasRequestCriteriaTrait;

            public function exposeValidateRepository(?Repository $repository = null): Repository
            {
                return $this->validateRepository($repository);
            }
        };

        $this->expectException(CoreInternalErrorException::class);
        $this->expectExceptionMessage('No protected or public accessible repository available');

        $nullRepoInstance->exposeValidateRepository();
    }

    public function testShouldDecodeSearchReturnsFalseWhenHashIdDisabled(): void
    {
        config(['apiato.hash-id' => false]);

        $request = new Request([$this->searchKey => 'id:123']);
        $this->app->instance(Request::class, $request);

        $result = $this->traitObject->exposeShouldDecodeSearch();

        self::assertFalse($result);
    }

    public function testShouldDecodeSearchReturnsFalseWhenNoSearchParameter(): void
    {
        $request = new Request();
        $this->app->instance(Request::class, $request);

        $result = $this->traitObject->exposeShouldDecodeSearch();

        self::assertFalse($result);
    }

    public function testShouldDecodeSearchReturnsTrueWhenHashIdEnabledAndSearchFilled(): void
    {
        $request = new Request([$this->searchKey => 'id:123']);
        $this->app->instance(Request::class, $request);

        $result = $this->traitObject->exposeShouldDecodeSearch();

        self::assertTrue($result);
    }

    public function testDecodeSearchQueryStringHandlesNonStringSearch(): void
    {
        $request = new Request();
        $request->query = new InputBag();
        $request->query->set($this->searchKey, null);

        $originalQuery = $request->query->all();
        $this->app->instance(Request::class, $request);

        $this->traitObject->exposeDecodeSearchQueryString();

        self::assertNull($request->query->get($this->searchKey));
        self::assertEquals($originalQuery, $request->query->all());
    }

    public function testDecodeSearchQueryStringHandlesEmptyStringSearch(): void
    {
        $request = new Request();
        $request->query = new InputBag();
        $request->query->set($this->searchKey, '');

        $originalQuery = $request->query->all();
        $this->app->instance(Request::class, $request);

        $this->traitObject->exposeDecodeSearchQueryString();

        self::assertSame('', $request->query->get($this->searchKey));
        self::assertEquals($originalQuery, $request->query->all());
    }

    public function testDecodeSearchQueryStringDecodesHashedIds(): void
    {
        $hashedId1 = $this->traitObject->publicEncode(123);
        $hashedId2 = $this->traitObject->publicEncode(456);
        $searchString = \sprintf('id:%s;role_id:%s', $hashedId1, $hashedId2);
        $expectedString = 'id:123;role_id:456';

        $request = new Request();
        $request->query = new InputBag();
        $request->query->set($this->searchKey, $searchString);

        $this->app->instance(Request::class, $request);

        $this->traitObject->exposeDecodeSearchQueryString();

        self::assertSame($expectedString, $request->query->get($this->searchKey));
    }

    public function testDecodeSearchQueryStringIgnoresBooleanValues(): void
    {
        $searchString = 'is_active:true;is_admin:false;status:1;flag:0';
        $request = new Request();
        $request->query = new InputBag();
        $request->query->set($this->searchKey, $searchString);

        $this->app->instance(Request::class, $request);

        $this->traitObject->exposeDecodeSearchQueryString();

        self::assertSame($searchString, $request->query->get($this->searchKey));
    }

    public function testDecodeSearchQueryStringIgnoresNumericValues(): void
    {
        $searchString = 'age:30;count:5';
        $request = new Request();
        $request->query = new InputBag();
        $request->query->set($this->searchKey, $searchString);

        $this->app->instance(Request::class, $request);

        $this->traitObject->exposeDecodeSearchQueryString();

        self::assertSame($searchString, $request->query->get($this->searchKey));
    }

    public function testDecodeSearchQueryStringHandlesComplexInput(): void
    {
        $hashedId1 = $this->traitObject->publicEncode(123);
        $hashedId2 = $this->traitObject->publicEncode(456);
        $invalidHash = 'invalid-hash';
        $searchString = \sprintf('id:%s;name:John;is_active:true;role_id:%s;age:30;token:%s', $hashedId1, $hashedId2, $invalidHash);
        $expectedString = 'id:123;name:John;is_active:true;role_id:456;age:30;token:' . $invalidHash;

        $request = new Request();
        $request->query = new InputBag();
        $request->query->set($this->searchKey, $searchString);

        $this->app->instance(Request::class, $request);

        $this->traitObject->exposeDecodeSearchQueryString();

        $expectedData = $this->parseSearchStringForTest($expectedString);
        $currentData = $this->parseSearchStringForTest($request->query->get($this->searchKey) ?? '');
        self::assertEquals($expectedData, $currentData);
    }

    #[DataProvider('searchDataProvider')]
    public function testParserSearchDataProcessesInputCorrectly(string $search, array $expected): void
    {
        $result = $this->traitObject->exposeParserSearchData($search);
        self::assertEquals($expected, $result);
    }

    #[DataProvider('decodedSearchValuesProvider')]
    public function testGetDecodedSearchValuesProcessesInputCorrectly(array $searchData, array $expected): void
    {
        $inputData = [];
        foreach ($searchData as $key => $value) {
            if ($value === '<<hash_me_1>>') {
                $inputData[$key] = $this->traitObject->publicEncode(123);
            } elseif ($value === '<<hash_me_2>>') {
                $inputData[$key] = $this->traitObject->publicEncode(456);
            } else {
                $inputData[$key] = $value;
            }
        }

        $result = $this->traitObject->exposeGetDecodedSearchValues($inputData);
        self::assertEquals($expected, $result);
    }

    #[DataProvider('buildSearchQueryProvider')]
    public function testBuildSearchQueryCreatesCorrectString(array $searchData, string $expected): void
    {
        $result = $this->traitObject->exposeBuildSearchQuery($searchData);

        $expectedData = $this->parseSearchStringForTest($expected);
        $currentData = $this->parseSearchStringForTest($result ?? '');
        self::assertEquals($expectedData, $currentData);
    }

    public static function searchDataProvider(): \Iterator
    {
        yield 'empty string' => [
            '',
            [],
        ];
        yield 'single pair' => [
            'user_id:123',
            ['user_id' => '123'],
        ];
        yield 'multiple pairs' => [
            'user_id:123;name:John;is_active:true',
            ['user_id' => '123', 'name' => 'John', 'is_active' => 'true'],
        ];
        yield 'with spaces' => [
            ' user_id : 123 ; name : John ',
            ['user_id' => '123', 'name' => 'John'],
        ];
        yield 'invalid format - no colon' => [
            'invalid_format',
            [],
        ];
        yield 'empty field name' => [
            ':value;name:John',
            ['name' => 'John'],
        ];
        yield 'mixed valid and invalid' => [
            'user_id:123;invalid;name:John',
            ['user_id' => '123', 'name' => 'John'],
        ];
        yield 'with colon in value' => [
            'time:12:30:45;id:123',
            ['time' => '12:30:45', 'id' => '123'],
        ];
    }

    public static function decodedSearchValuesProvider(): \Iterator
    {
        yield 'empty array' => [
            [],
            [],
        ];
        yield 'boolean values' => [
            ['is_active' => 'true', 'is_admin' => 'false', 'flag1' => '1', 'flag0' => '0'],
            ['is_active' => 'true', 'is_admin' => 'false', 'flag1' => '1', 'flag0' => '0'],
        ];
        yield 'numeric values' => [
            ['age' => '30', 'count' => '5'],
            ['age' => '30', 'count' => '5'],
        ];
        yield 'string values' => [
            ['name' => 'John', 'email' => 'test@example.com'],
            ['name' => 'John', 'email' => 'test@example.com'],
        ];
        yield 'hashed id is decoded' => [
            ['user_id' => '<<hash_me_1>>'],
            ['user_id' => 123],
        ];
        yield 'mixed hashed and regular values' => [
            ['id' => '<<hash_me_1>>', 'name' => 'Test', 'status' => '1'],
            ['id' => 123, 'name' => 'Test', 'status' => '1'],
        ];
        yield 'multiple hashes' => [
            ['user' => '<<hash_me_1>>', 'role' => '<<hash_me_2>>'],
            ['user' => 123, 'role' => 456],
        ];
        yield 'invalid hash' => [
            ['token' => 'invalid-hash-string'],
            ['token' => 'invalid-hash-string'],
        ];
        yield 'mixed valid and invalid hash' => [
            ['valid' => '<<hash_me_1>>', 'invalid' => 'invalid-hash'],
            ['valid' => 123, 'invalid' => 'invalid-hash'],
        ];
    }

    public static function buildSearchQueryProvider(): \Iterator
    {
        yield 'empty array' => [
            [],
            '',
        ];
        yield 'single pair' => [
            ['user_id' => 123],
            'user_id:123',
        ];
        yield 'multiple pairs' => [
            ['user_id' => 123, 'name' => 'John', 'is_active' => true],
            'user_id:123;name:John;is_active:1',
        ];
        yield 'boolean values' => [
            ['is_active' => true, 'is_admin' => false],
            'is_active:1;is_admin:',
        ];
        yield 'numeric strings' => [
            ['code' => '007', 'count' => '50'],
            'code:007;count:50',
        ];
        yield 'mixed values' => [
            ['user_id' => 123, 'name' => 'John', 'is_active' => true, 'count' => 5],
            'user_id:123;name:John;is_active:1;count:5',
        ];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['apiato.hash-id' => true]);
        $this->searchKey = config('repository.criteria.params.search', 'search');

        $this->mockRepository = $this->mock(Repository::class);

        $this->traitObject = new class ($this->mockRepository) {
            use HasRequestCriteriaTrait;

            public function __construct(public ?Repository $repository)
            {
            }

            public function exposeValidateRepository(?Repository $repository = null): Repository
            {
                return $this->validateRepository($repository);
            }

            public function exposeShouldDecodeSearch(): bool
            {
                return $this->shouldDecodeSearch();
            }

            public function exposeDecodeSearchQueryString(): void
            {
                $this->decodeSearchQueryString();
            }

            public function exposeParserSearchData(string $search): array
            {
                return $this->parserSearchData($search);
            }

            public function exposeGetDecodedSearchValues(array $searchData): array
            {
                return $this->getDecodedSearchValues($searchData);
            }

            public function exposeBuildSearchQuery(array $searchData): string
            {
                return $this->buildSearchQuery($searchData);
            }

            public function publicEncode(int $id): string
            {
                return $this->encode($id);
            }
        };
    }

    private function parseSearchStringForTest(string $search): array
    {
        $data = [];

        if ($search === '' || $search === '0' || !str_contains($search, ':')) {
            return $data;
        }

        $pairs = explode(';', $search);
        foreach ($pairs as $pair) {
            if ($pair === '' || $pair === '0') {
                continue;
            }

            if (!str_contains($pair, ':')) {
                continue;
            }

            $parts = explode(':', $pair, 2);

            if (\count($parts) !== 2) {
                continue;
            }

            $field = trim($parts[0]);

            if ($field !== '') {
                $data[$field] = trim($parts[1]);
            }
        }

        ksort($data);

        return $data;
    }
}
