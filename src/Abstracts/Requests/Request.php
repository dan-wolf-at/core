<?php

declare(strict_types=1);

namespace Apiato\Core\Abstracts\Requests;

use Apiato\Core\Abstracts\Models\UserModel as User;
use Apiato\Core\Exceptions\IncorrectIdException;
use Apiato\Core\Traits\HashIdTrait;
use Apiato\Core\Traits\SanitizerTrait;
use Illuminate\Foundation\Http\FormRequest as LaravelRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Throwable;
use UnitEnum;

abstract class Request extends LaravelRequest
{
    use HashIdTrait;
    use SanitizerTrait;

    /**
     * Roles and/or Permissions that has access to this request.
     *
     * @example ['permissions' => 'create-users', 'roles' => 'admin|manager']
     * @example ['permissions' => null, 'roles' => 'admin']
     * @example ['permissions' => ['create-users'], 'roles' => null]
     *
     * @var array<string, string|array<string>|null>
     */
    protected array $access = [
        'permissions' => null,
        'roles'       => null,
    ];

    /**
     * Id's that needs decoding before applying the validation rules.
     *
     * @example ['id']
     *
     * @var string[]
     */
    protected array $decode = [];

    /**
     * Defining the URL parameters (`/stores/{slug}/items`) allows applying
     * validation rules on them and allows accessing them like request data.
     *
     * For example, you can use the `exists` validation rule on the `slug` parameter.
     * And you can access the `slug` parameter using `$request->slug`.
     *
     * @example ['slug']
     *
     * @var string[]
     */
    protected array $urlParameters = [];

    /**
     * To be used mainly from unit tests.
     */
    public static function injectData(
        array $parameters = [],
        null|User $user = null,
        array $cookies = [],
        array $files = [],
        array $server = [],
    ): static {
        // If user is passed, will be returned when asking for the authenticated user using `\Auth::user()`
        if ($user !== null) {
            $app = App::getInstance();
            $app['auth']->guard($driver = 'api')->setUser($user);
            $app['auth']->shouldUse($driver);
        }

        // For now doesn't matter which URI or Method is used.
        $request = parent::create('/', SymfonyRequest::METHOD_GET, $parameters, $cookies, $files, $server);

        $request->setUserResolver(static fn (): ?User => $user);

        return $request;
    }

    /**
     * Add properties to the request that are not part of the request body
     * but are needed for the request to be processed.
     * For example, in the unit tests, we can add the url parameters to the request which is not part of the request body.
     * It is best used with the `injectData` method.
     *
     * @return $this
     */
    public function withUrlParameters(array $properties): self
    {
        foreach ($properties as $key => $value) {
            $this->{$key} = $value;
        }

        return $this;
    }

    public function getAccessArray(): array
    {
        return $this->access;
    }

    public function getDecodeArray(): array
    {
        return $this->decode;
    }

    public function getUrlParametersArray(): array
    {
        return $this->urlParameters;
    }

    /**
     * Check if a user has permission to perform an action.
     * User can set multiple permissions (separated with "|") and if the user has
     * any of the permissions, he will be authorized to proceed with this action.
     */
    public function hasAccess(null|User $user = null): bool
    {
        // If not in parameters, take from the request object {$this}
        $user = $user instanceof User ? $user : $this->user();

        if ($user) {
            $autoAccessRoles = config('apiato.requests.allow-roles-to-access-all-routes');

            // There are some roles defined that will automatically grant access
            if (!empty($autoAccessRoles)) {
                $hasAutoAccessByRole = $user->hasAnyRole($autoAccessRoles);

                if ($hasAutoAccessByRole) {
                    return true;
                }
            }
        }

        // Check if the user has any role / permission to access the route
        $hasAccess = array_merge(
            $this->hasAnyPermissionAccess($user),
            $this->hasAnyRoleAccess($user),
        );

        // Allow access if user has access to any of the defined roles or permissions. Or if $hasAccess are empty.
        return $hasAccess === [] || \in_array(true, $hasAccess, true);
    }

    /**
     * Maps Keys in the Request.
     *
     * For example, ['data.attributes.name' => 'firstname'] would map the field [data][attributes][name] to [firstname].
     * Note that the old value (data.attributes.name) is removed the original request - this method manipulates the request!
     * Be sure you know what you do!
     *
     * @throws IncorrectIdException
     * @throws Throwable
     */
    public function mapInput(array $fields): void
    {
        $data = $this->all();

        foreach ($fields as $oldKey => $newKey) {
            // The key to be mapped does not exist - skip it
            if (!Arr::has($data, $oldKey)) {
                continue;
            }

            // Set the new field and remove the old one
            Arr::set($data, $newKey, Arr::get($data, $oldKey));
            Arr::forget($data, $oldKey);
        }

        // Overwrite the initial request
        $this->replace($data);
    }

    /**
     * Overriding this function to modify any user input before
     * applying the validation rules.
     *
     * @param array|null $keys
     *
     * @throws IncorrectIdException
     * @throws Throwable
     */
    public function all($keys = null): array
    {
        $requestData = parent::all($keys);

        $requestData = $this->mergeUrlParametersWithRequestData($requestData);

        return $this->decodeHashedIdsBeforeValidation($requestData);
    }

    /**
     * This method mimics the $request->input() method but works on the "decoded" values.
     *
     * @throws IncorrectIdException
     * @throws Throwable
     */
    public function getInputByKey($key = null, $default = null): mixed
    {
        return data_get($this->all(), $key, $default);
    }

    protected function hasAnyPermissionAccess(?User $user): array
    {
        // If not in parameters, take from the request object {$this}
        $user = $user instanceof User ? $user : $this->user();

        $permissions = $this->preparingAccessValues('permissions');

        return array_map(static fn ($permission) => $user?->hasPermissionTo($permission), $permissions);
    }

    protected function hasAnyRoleAccess(?User $user): array
    {
        // If not in parameters, take from the request object {$this}
        $user = $user instanceof User ? $user : $this->user();
        $roles = $this->preparingAccessValues('roles');

        return array_map(static fn ($role) => $user?->hasRole($role), $roles);
    }

    protected function preparingAccessValues(string $key): array
    {
        if (\array_key_exists($key, $this->access) === false) {
            return [];
        }

        $accessValues = $this->access[$key];

        if ($accessValues === '' || $accessValues === null || $accessValues === []) {
            return [];
        }

        // If a string and this string contains a delimiter, then convert this to an array.
        if (\is_string($accessValues)) {
            $accessValues = explode('|', $accessValues);
        }

        // If it is not already an array, wrap it with an array.
        $accessValues = Arr::wrap($accessValues);

        // If an element of an array is an enumeration, then there is a need to cast it to a string.
        return array_map(
            static fn (string|int|UnitEnum $accessValue): string|int => $accessValue instanceof UnitEnum ?
                $accessValue->value
                : $accessValue,
            $accessValues
        );
    }

    /**
     * Apply validation rules to the ID's in the URL, since Laravel
     * doesn't validate them by default!
     * Now you can use validation rules like this: `'id' => 'required|integer|exists:items,id'`.
     */
    protected function mergeUrlParametersWithRequestData(array $requestData): array
    {
        if (property_exists($this, 'urlParameters') && $this->urlParameters !== []) {
            foreach ($this->urlParameters as $urlParameter) {
                $requestData[$urlParameter] = $this->route($urlParameter);
            }
        }

        return $requestData;
    }

    /**
     * Used from the `authorize` function if the Request class.
     * To call functions and compare their bool responses to determine
     * if the user can proceed with the request or not.
     */
    protected function check(array $functions): bool
    {
        $orIndicator = '|';
        $returns = [];

        // iterate all functions in the array
        foreach ($functions as $function) {
            // in case the value doesn't contain a separator (single function per key)
            if (\in_array(strpos((string) $function, $orIndicator), [0, false], true)) {
                // simply call the single function and store the response.
                $returns[] = $this->{$function}();
            } else {
                // in case the value contains a separator (multiple functions per key)
                $orReturns = [];

                // iterate over each function in the key
                foreach (explode($orIndicator, (string) $function) as $orFunction) {
                    // dynamically call each function
                    $orReturns[] = $this->{$orFunction}();
                }

                // if in_array returned `true` means at least one function returned `true` thus return `true` to allow access.
                // if in_array returned `false` means no function returned `true` thus return `false` to prevent access.
                // return single boolean for all the functions found inside the same key.
                $returns[] = \in_array(true, $orReturns, true);
            }
        }

        // if in_array returned `true` means a function returned `false` thus return `false` to prevent access.
        // if in_array returned `false` means all functions returned `true` thus return `true` to allow access.
        // return the final boolean
        return \in_array(false, $returns, true) === false;
    }
}
