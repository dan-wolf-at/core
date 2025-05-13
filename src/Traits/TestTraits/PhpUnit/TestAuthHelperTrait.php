<?php

declare(strict_types=1);

namespace Apiato\Core\Traits\TestTraits\PhpUnit;

use Apiato\Core\Abstracts\Models\UserModel;
use Faker\Generator;
use Illuminate\Support\Facades\Hash;

trait TestAuthHelperTrait
{
    public string $userName = 'name';

    /**
     * Logged in user object.
     */
    protected null|UserModel $testingUser = null;

    /**
     * User class used by factory to create testing user.
     */
    protected null|string $userClass = null;

    /**
     * The Faker instance.
     *
     * @var Generator
     */
    protected $faker;

    /**
     * Roles and permissions, to be attached on the user.
     */
    protected array $access = [
        'permissions' => null,
        'roles'       => null,
    ];

    /**
     * Same as `getTestingUser()` but always overrides the User Access
     * (roles and permissions) with null. So the user can be used to test
     * if unauthorized user tried to access your protected endpoint.
     */
    public function getTestingUserWithoutAccess(null|array|UserModel $userDetails = null, bool $createUserAsAdmin = false): UserModel
    {
        return $this->getTestingUser($userDetails, $this->getNullAccess(), $createUserAsAdmin);
    }

    /**
     * Try to get the last logged-in User, if not found then create new one.
     * Note: if $userDetails are provided it will always create new user, even
     * if another one was previously created during the execution of your test.
     *
     * By default, Users will be given the Roles and Permissions found in the class
     * `$access` property. But the $access parameter can be used to override the
     * defined roles and permissions in the `$access` property of your class.
     *
     * @param array|UserModel|null $userDetails       what to be attached on the User object
     * @param array|null           $access            roles and permissions you'd like to provide this user with
     */
    public function getTestingUser(null|array|UserModel $userDetails = null, null|array $access = null, bool $createUserAsAdmin = false): UserModel
    {
        $this->userClass ??= config('apiato.tests.user-class');

        if (!$this->userClass) {
            throw new \RuntimeException('User class is not defined in the test class');
        }

        if (\is_null($userDetails)) {
            return $this->findOrCreateTestingUser($userDetails, $access);
        }

        return $this->createTestingUser($userDetails, $access);
    }

    private function findOrCreateTestingUser(null|array|UserModel $userDetails = null, null|array $access = null): UserModel
    {
        return $this->testingUser ?: $this->createTestingUser($userDetails, $access);
    }

    private function createTestingUser(null|array|UserModel $userDetails = null, null|array $access = null): UserModel
    {
        // Create new user
        $user = $userDetails instanceof UserModel ? $userDetails : $this->factoryCreateUser($userDetails);

        // Authentication the user
        $this->actingAs($user, 'api');

        // Set the created user
        return $this->testingUser = $user;
    }

    private function factoryCreateUser(null|array $userDetails = null): UserModel
    {
        /** @var UserModel $user */
        $user = str_replace('::class', '', $this->userClass);

        return $user::factory()->create($this->prepareUserDetails($userDetails));
    }

    private function prepareUserDetails(null|array $userDetails = null): array
    {
        $defaultUserDetails = [
            $this->userName => $this->faker->name,
            'email'         => $this->faker->email,
            'password'      => 'testing-password',
        ];

        // if no user detail provided, use the default details, to find the password or generate one before encoding it
        return $this->prepareUserPassword($userDetails !== null && $userDetails !== [] ? $userDetails : $defaultUserDetails);
    }

    private function prepareUserPassword(null|array $userDetails): null|array
    {
        // Get password from the user details or generate one
        $password = $userDetails['password'] ?? $this->faker->password;

        // Hash the password and set it back at the user details
        $userDetails['password'] = Hash::make($password);

        return $userDetails;
    }

    private function getAccess(): null|array
    {
        return $this->access ?? null;
    }

    private function getNullAccess(): array
    {
        return [
            'permissions' => null,
            'roles'       => null,
        ];
    }
}
