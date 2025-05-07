<?php

declare(strict_types=1);

namespace Apiato\Core\Tests\Unit\Abstracts\Repositories;

use Apiato\Core\Abstracts\Repositories\Repository;
use Apiato\Core\Tests\Infrastructure\Doubles\Book;
use Apiato\Core\Tests\Infrastructure\Doubles\BookFactory;
use Apiato\Core\Tests\Infrastructure\Doubles\User;
use Apiato\Core\Tests\Infrastructure\Doubles\UserFactory;
use Apiato\Core\Tests\Infrastructure\Doubles\UserRepository;
use Apiato\Core\Tests\Unit\UnitTestCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Repository::class)]
final class RepositoryTest extends UnitTestCase
{
    public static function includeDataProvider(): \Iterator
    {
        yield 'single relation' => [
            'books',
            ['books'],
            [],
            ['children', 'parent'],
        ];
        yield 'works with duplicate include' => [
            'books,books',
            ['books'],
            [],
            ['children', 'parent'],
        ];
        yield 'multiple relations' => [
            'books,children',
            ['books', 'children'],
            [],
            ['parent'],
        ];
        yield 'single nested relation' => [
            'books.author',
            ['books'],
            ['author'],
            ['children', 'parent'],
        ];
        yield 'multiple nested relations' => [
            'books.author.children,children.parent',
            ['books', 'children'],
            ['author'],
            ['parent'],
        ];
        yield 'multiple and single nested relations' => [
            'parent,books.author',
            ['parent', 'books'],
            ['author'],
            ['children'],
        ];
    }

    #[DataProvider('includeDataProvider')]
    public function testEagerLoadSingleRelationRequestedViaRequest(
        string $include,
        array $userMustLoadRelations,
        array $booksMustLoadRelations,
        array $mustNotLoadRelations,
    ): void {
        request()->merge(['include' => $include]);
        UserFactory::new()
            ->has(
                UserFactory::new()
                    ->has(BookFactory::new()->count(3)),
                'children',
            )->has(BookFactory::new()->count(3))
            ->createOne();
        $repository = new class (app()) extends UserRepository {
            public function shouldEagerLoadIncludes(): bool
            {
                return true;
            }
        };

        $result = $repository->all();

        $result->each(static function (User $user) use ($userMustLoadRelations, $booksMustLoadRelations, $mustNotLoadRelations): void {
            foreach ($userMustLoadRelations as $userMustLoadRelation) {
                self::assertTrue($user->relationLoaded($userMustLoadRelation));
            }

            foreach ($booksMustLoadRelations as $bookMustLoadRelation) {
                $user->books->each(static function (Book $book) use ($bookMustLoadRelation): void {
                    self::assertTrue($book->relationLoaded($bookMustLoadRelation));
                });
            }

            foreach ($mustNotLoadRelations as $mustNotLoadRelation) {
                self::assertFalse($user->relationLoaded($mustNotLoadRelation));
            }
        });
    }

    public function testMultipleEagerLoadAppliesAllEagerLoads(): void
    {
        UserFactory::new()
            ->has(
                UserFactory::new()
                    ->has(BookFactory::new()->count(3)),
                'children',
            )->has(BookFactory::new()->count(3))
            ->createOne();
        $repository = new class (app()) extends UserRepository {
            public function shouldEagerLoadIncludes(): bool
            {
                return true;
            }
        };

        /** @var Collection<int, User> $result */
        $result = $repository->with('books')->with('children.books')->all();

        $result->each(static function (User $user): void {
            self::assertTrue($user->relationLoaded('books'));
            self::assertTrue($user->relationLoaded('children'));
            foreach ($user->children as $child) {
                self::assertTrue($child->relationLoaded('books'));
            }
        });
    }

    public function testCanCache(): void
    {
        config()->set('repository.cache.enabled', true);
        config()->set('repository.cache.minutes', 1);
        config()->set('cache.default', 'array');

        $user = UserFactory::new()->createOne();
        $userRepository = $this->app->make(UserRepository::class);

        DB::enableQueryLog();
        $firstUser = $userRepository->find($user->id);
        self::assertCount(1, DB::getQueryLog(), 'The first call must query the database to store the result in cache.');

        DB::flushQueryLog();
        $secondUser = $userRepository->find($user->id);
        self::assertCount(0, DB::getQueryLog(), 'The second call should be served from cache without touching the database.');
        self::assertEquals($firstUser->toArray(), $secondUser->toArray());

        $updatedName = 'new name';
        $userRepository->update(['name' => $updatedName], $user->id);

        DB::flushQueryLog();
        $thirdUser = $userRepository->find($user->id);
        self::assertCount(1, DB::getQueryLog(), 'After update() the cache must be flushed, so a fresh DB query is expected.');
        self::assertEquals($updatedName, $thirdUser->name);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('fractal.auto_includes.request_key', 'include');
    }
}
