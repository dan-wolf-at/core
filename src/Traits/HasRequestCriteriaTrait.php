<?php

declare(strict_types=1);

namespace Apiato\Core\Traits;

use Apiato\Core\Abstracts\Repositories\Repository;
use Apiato\Core\Exceptions\CoreInternalErrorException;
use JetBrains\PhpStorm\Deprecated;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Vinkla\Hashids\Facades\Hashids;

trait HasRequestCriteriaTrait
{
    /**
     * @throws CoreInternalErrorException
     * @throws RepositoryException
     */
    #[Deprecated(
        reason: 'since Apiato 12.2.0, Use addRequestCriteria() on the Repository instead.
        Will be removed from Tasks and Actions.',
        replacement: '%class%->repository->addRequestCriteria();',
    )]
    public function addRequestCriteria($repository = null, array $fieldsToDecode = ['id']): static
    {
        $validatedRepository = $this->validateRepository($repository);
        $validatedRepository->pushCriteria(app(RequestCriteria::class));
        if ($this->shouldDecodeSearch()) {
            $this->decodeSearchQueryString($fieldsToDecode);
        }

        return $this;
    }

    /**
     * @throws CoreInternalErrorException
     */
    public function removeRequestCriteria($repository = null): static
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
    private function validateRepository($repository): Repository
    {
        $validatedRepository = $repository;

        // check if we have a "custom" repository
        if (is_null($repository)) {
            if (!isset($this->repository)) {
                throw new CoreInternalErrorException('No protected or public accessible repository available');
            }

            $validatedRepository = $this->repository;
        }

        // check, if the validated repository is null
        if (is_null($validatedRepository)) {
            throw new CoreInternalErrorException();
        }

        // check if it is a Repository class
        if (!($validatedRepository instanceof Repository)) {
            throw new CoreInternalErrorException();
        }

        return $validatedRepository;
    }

    private function shouldDecodeSearch(): bool
    {
        return $this->hashIdEnabled() && $this->isSearching(request()?->query() ?? []);
    }

    private function hashIdEnabled(): bool
    {
        return config('apiato.hash-id');
    }

    private function isSearching(array $query): bool
    {
        return array_key_exists('search', $query) && $query['search'];
    }

    private function decodeSearchQueryString(array $fieldsToDecode): void
    {
        $query = request()?->query();
        $searchQuery = $query['search'];

        $decodedValue = $this->decodeValue($searchQuery);
        $decodedData = $this->decodeData($fieldsToDecode, $searchQuery);

        $decodedQuery = $this->arrayToSearchQuery($decodedData);

        if ($decodedValue) {
            if (empty($decodedQuery)) {
                $decodedQuery .= $decodedValue;
            } else {
                $decodedQuery .= (';' . $decodedValue);
            }
        }

        $query['search'] = $decodedQuery;

        request()->query->replace($query);
    }

    private function decodeValue(string $searchQuery): null|string
    {
        $searchValue = $this->parserSearchValue($searchQuery);

        if ($searchValue) {
            $decodedId = Hashids::decode($searchValue);
            if ($decodedId) {
                return $decodedId[0];
            }
        }

        return $searchValue;
    }

    private function parserSearchValue($search)
    {
        if (strpos((string) $search, ';') || strpos((string) $search, ':')) {
            $values = explode(';', (string) $search);
            foreach ($values as $value) {
                $s = explode(':', $value);
                if (count($s) === 1) {
                    return $s[0];
                }
            }

            return null;
        }

        return $search;
    }

    private function decodeData(array $fieldsToDecode, string $searchQuery): array
    {
        $searchArray = $this->parserSearchData($searchQuery);

        foreach ($fieldsToDecode as $fieldToDecode) {
            if (array_key_exists($fieldToDecode, $searchArray)) {
                if (empty(Hashids::decode($searchArray[$fieldToDecode]))) {
                    throw new \InvalidArgumentException(sprintf('Only hash ids are allowed. %s:%s', $fieldToDecode, $searchArray[$fieldToDecode]));
                }

                $searchArray[$fieldToDecode] = Hashids::decode($searchArray[$fieldToDecode])[0];
            }
        }

        return $searchArray;
    }

    private function parserSearchData($search): array
    {
        $searchData = [];

        if (strpos((string) $search, ':')) {
            $fields = explode(';', (string) $search);

            foreach ($fields as $row) {
                try {
                    [$field, $value] = explode(':', $row);
                    $searchData[$field] = $value;
                } catch (\Throwable) {
                    // Surround offset error
                }
            }
        }

        return $searchData;
    }

    private function arrayToSearchQuery(array $decodedSearchArray): string
    {
        $decodedSearchQuery = '';

        $fields = array_keys($decodedSearchArray);
        $length = \count($fields);
        foreach ($fields as $i => $iValue) {
            $field = $iValue;
            $decodedSearchQuery .= sprintf('%s:%s', $field, $decodedSearchArray[$field]);

            if ($length !== 1 && $i < $length - 1) {
                $decodedSearchQuery .= ';';
            }
        }

        return $decodedSearchQuery;
    }
}
