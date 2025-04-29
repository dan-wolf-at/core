<?php

declare(strict_types=1);

namespace Apiato\Core\Traits\TestTraits\PhpUnit;

use Apiato\Core\Abstracts\Models\Model;
use Illuminate\Auth\Access\Gate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use JetBrains\PhpStorm\Deprecated;
use Mockery\MockInterface;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionException;

trait TestAssertionHelperTrait
{
    /**
     * Assert that the Gate::allows() method is called once with the given arguments.
     *
     * @return Gate|(Gate&MockObject)|MockObject
     *
     * @throws Exception
     */
    protected function getGateMock(string $policyMethodName, ...$args)
    {
        $gateMock = $this->createMock(Gate::class);
        $gateMock->expects($this->once())
            ->method('allows')
            ->with($policyMethodName, ...$args)
            ->willReturn(true);

        return $gateMock;
    }

    /**
     * Assert that the model casts field is empty.
     * By default, the model casts will have 'id' and 'deleted_at' fields (given model is soft deletable).
     * This method will exclude those fields from the assertion.
     * If you want to add more fields, you can pass them as an array.
     */
    protected function assertModelCastsIsEmpty(Model $model, array ...$extraDefaultField): void
    {
        $defaultCasts = [
            'id' => 'int',
            'deleted_at' => 'datetime',
        ];

        $casts = [...$defaultCasts, ...$extraDefaultField];

        $this->assertEmpty(array_diff($model->getCasts(), $casts));
    }

    /**
     * Check if the given id is in the given model collection by comparing hashed ids.
     *
     * @param Collection|array $ids either a collection of models or an array of ids
     *
     * @example $this->inIds($hashedId, $collectionOfModels);
     */
    #[Deprecated(reason: 'Wrong method location and bad design. Use the "containsHashedId" method from the EloquentCollection instead.')]
    protected function inIds(string $hashedId, Collection|array $ids): bool
    {
        if ($ids instanceof Collection) {
            return $ids->contains('id', $this->decode($hashedId));
        }

        return in_array($this->decode($hashedId), $ids, true);
    }

    /**
     * Assert if the given database table has the expected columns with the expected types.
     *
     * @param string $table the table name
     * @param array<string, string> $expectedColumns The key is the column name and the value is the column type.
     *
     * Example: $this->assertDatabaseTable('users', ['id' => 'bigint']);
     */
    protected function assertDatabaseTable(string $table, array $expectedColumns): void
    {
        $this->assertSameSize($expectedColumns, Schema::getColumnListing($table), sprintf("Column count mismatch for '%s' table.", $table));
        foreach ($expectedColumns as $column => $type) {
            $this->assertTrue(Schema::hasColumn($table, $column), sprintf("Column '%s' not found in '%s' table.", $column, $table));
            $this->assertEquals($type, Schema::getColumnType($table, $column), sprintf("Column '%s' in '%s' table does not match expected %s type.", $column, $table, $type));
        }
    }

    /**
     * Get the given inaccessible (private/protected) property value.
     *
     * @throws ReflectionException
     */
    protected function getInaccessiblePropertyValue(object $object, string $property): mixed
    {
        $reflectionClass = new \ReflectionClass($object);

        return $reflectionClass
            ->getProperty($property)
            ->getValue($object);
    }

    /**
     * Create a spy for an Action, SubAction or a Task that uses a repository.
     *
     * @param string $className the Action, SubAction or a Task class name
     * @param string $repositoryClassName the repository class name
     */
    protected function createSpyWithRepository(string $className, string $repositoryClassName, bool $allowRun = true): MockInterface
    {
        /** @var MockInterface $legacyMock */
        $legacyMock = \Mockery::mock($className, [app($repositoryClassName)])
            ->shouldIgnoreMissing(null, true)
            ->makePartial();

        if ($allowRun) {
            $legacyMock->allows('run')->andReturn();
        }

        $this->swap($className, $legacyMock);

        return $legacyMock;
    }

    /**
     * Mock a repository and assert that the given criteria is pushed to it.
     *
     * @param string $repositoryClassName the repository class name
     * @param string $criteriaClassName the criteria class name
     * @param array<string, mixed>|null $criteriaArgs the criteria constructor arguments
     *
     * @return MockInterface repository mock
     *
     * @example $this->
     * assertCriteriaPushedToRepository(UserRepository::class, SearchUsersCriteria::class, ['parameterName' => 'value']);
     */
    protected function assertCriteriaPushedToRepository(string $repositoryClassName, string $criteriaClassName, array|null $criteriaArgs = null): MockInterface
    {
        $repositoryMock = $this->mock($repositoryClassName);

        if (is_null($criteriaArgs)) {
            $repositoryMock->expects('pushCriteria')->once();
        } else {
            $repositoryMock->expects('pushCriteriaWith')->once()->with($criteriaClassName, $criteriaArgs);
        }

        return $repositoryMock;
    }

    /**
     * Assert that no criteria are pushed to the repository.
     *
     * @param string $repositoryClassName the repository class name
     *
     * @return MockInterface repository mock
     */
    protected function assertNoCriteriaPushedToRepository(string $repositoryClassName): MockInterface
    {
        $repositoryMock = $this->mock($repositoryClassName);
        $repositoryMock->expects('pushCriteria')->never();

        return $repositoryMock;
    }

    /**
     * Allow "addRequestCriteria" method invocation on the repository mock.
     * This is particularly useful when you want to test a repository that uses the RequestCriteria
     * (e.g., for search and filtering).
     */
    protected function allowAddRequestCriteriaInvocation(MockInterface $repositoryMock): void
    {
        $repositoryMock->allows('addRequestCriteria')->andReturnSelf();
    }
}
