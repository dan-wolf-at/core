<?php

declare(strict_types=1);

namespace Apiato\Core\Traits;

use Apiato\Core\Abstracts\Repositories\Repository;
use Apiato\Core\Exceptions\CoreInternalErrorException;
use Illuminate\Http\Request;
use JetBrains\PhpStorm\Deprecated;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;

trait HasRequestCriteriaTrait
{
    use HashIdTrait;

    /**
     * @throws CoreInternalErrorException
     * @throws RepositoryException
     */
    #[Deprecated(
        reason: 'since Apiato 12.2.0, Use addRequestCriteria() on the Repository instead.
        Will be removed from Tasks and Actions.',
        replacement: '%class%->repository->addRequestCriteria();',
    )]
    public function addRequestCriteria(?Repository $repository = null): static
    {
        $validatedRepository = $this->validateRepository($repository);

        if ($this->shouldDecodeSearch()) {
            $this->decodeSearchQueryString();
        }

        $validatedRepository->pushCriteria(app(RequestCriteria::class));

        return $this;
    }

    /**
     * @throws CoreInternalErrorException
     */
    public function removeRequestCriteria(?Repository $repository = null): static
    {
        $validatedRepository = $this->validateRepository($repository);
        $validatedRepository->popCriteria(RequestCriteria::class);

        return $this;
    }

    /**
     * Validates, if the given Repository exists or uses $this->repository on the Task/Action to apply functions.
     *
     * @throws CoreInternalErrorException
     */
    private function validateRepository(?Repository $repository): Repository
    {
        $validatedRepository = $repository;

        // Check if we have a "custom" repository
        if (\is_null($repository)) {
            if (!isset($this->repository)) {
                throw new CoreInternalErrorException('No protected or public accessible repository available');
            }

            $validatedRepository = $this->repository;
        }

        // Check, if the validated repository is null
        if (\is_null($validatedRepository)) {
            throw new CoreInternalErrorException();
        }

        // Check if it is a Repository class
        if (!($validatedRepository instanceof Repository)) {
            throw new CoreInternalErrorException();
        }

        return $validatedRepository;
    }

    private function shouldDecodeSearch(): bool
    {
        if (config('apiato.hash-id', false) === false) {
            return false;
        }

        $searchKey = config('repository.criteria.params.search', 'search');
        /** @var Request $request */
        $request = app(Request::class);

        return $request->filled($searchKey);
    }

    /**
     * Decodes hashed IDs and processes boolean values
     * within field:value pairs of the request's ‘search’ parameter.
     * Modifies the current Request object if changes have been made.
     *
     * Without decoding the encoded ID's you won't be able to use
     * repository search features like `?search=user_id:hash_id;other_id:other_hash_id`.
     */
    private function decodeSearchQueryString(): void
    {
        /** @var Request $request */
        $request = app(Request::class);
        $searchKey = config('repository.criteria.params.search', 'search');
        $searchQuery = $request->get($searchKey);

        if (is_string($searchQuery) === false || $searchQuery === '') {
            return;
        }

        $searchData = $this->parserSearchData($searchQuery);
        $decodedData = $this->decodeSearchValues($searchData);

        if ($decodedData !== $searchData) {
            $newSearchQuery = $this->buildSearchQuery($decodedData);

            $query = $request->query();
            $query[$searchKey] = $newSearchQuery;

            $request->query->replace($query);
        }
    }

    private function parserSearchData(string $search): array
    {
        $searchData = [];

        if (str_contains($search, ':') === false) {
            return $searchData;
        }

        $fields = explode(';', $search);

        foreach ($fields as $field) {
            if (str_contains($field, ':') === false) {
                continue;
            }

            $parts = explode(':', $field, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $field = trim($parts[0]);
            if ($field === '') {
                continue;
            }

            $searchData[$field] = trim($parts[1]);
        }

        return $searchData;
    }

    private function decodeSearchValues(array $searchData): array
    {
        if ($searchData === []) {
            return $searchData;
        }

        foreach ($searchData as $field => $value) {
            $isBool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (isset($isBool) || is_numeric($value)) {
                continue;
            }

            $decodedId = $this->decode($value);
            if ($decodedId === null) {
                continue;
            }

            $searchData[$field] = $decodedId;
        }

        return $searchData;
    }

    /**
     * Reconstructs the search string from an array of field => value pairs.
     */
    private function buildSearchQuery(array $searchData): string
    {
        $parts = [];
        foreach ($searchData as $field => $value) {
            $parts[] = sprintf('%s:%s', $field, $value);
        }

        return implode(';', $parts);
    }
}
