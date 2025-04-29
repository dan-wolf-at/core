<?php

declare(strict_types=1);

namespace Apiato\Core\Traits;

use Apiato\Core\Exceptions\CoreInternalErrorException;
use Apiato\Core\Exceptions\IncorrectIdException;
use Vinkla\Hashids\Facades\Hashids;

trait HashIdTrait
{
    /**
     * Endpoint to be skipped from decoding their ID's (example for external ID's).
     */
    private array $skippedEndpoints = [
        // 'orders/{id}/external',
    ];

    /**
     * TODO: BC: This method is only used on Models and should be moved there
     * Hashes the value of a field (e.g., ID).
     * Will be used by the Eloquent Models (since it's used as trait there).
     *
     * @param string|null $field The field of the model to be hashed
     */
    public function getHashedKey(null|string $field = null): null|string|int
    {
        // If no key is set, use the default key name (i.e., id)
        if ($field === null) {
            $field = $this->getKeyName();
        }

        // We need to get the VALUE for this KEY (model field)
        $value = $this->getAttribute($field);

        // Hash the ID only if hash-id enabled in the config
        if (config('apiato.hash-id')) {
            if ($value === null) {
                return null;
            }

            return $this->encoder($value);
        }

        return $value;
    }

    /**
     * @param int $id
     */
    public function encoder($id): string
    {
        return Hashids::encode($id);
    }

    /**
     * @param int $id
     */
    public function encode($id): string
    {
        return $this->encoder($id);
    }

    public function decodeArray(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            $result[] = $this->decode($id);
        }

        return $result;
    }

    /**
     * If the decoded id is bigger than PHP_INT_MAX, the decoder will return a string
     * we will cut that off from propagating, because such big numerical identifiers
     * are not practically used
     *
     * if the id is not decodable, null will be returned
     */
    public function decode(null|string $id): null|int
    {
        // Check if passed as null, (could be an optional decodable variable).
        if ($id === null || strtolower($id) === 'null') {
            return null;
        }

        // Do the decoding if the ID looks like a hashed one.
        $decoded = $this->decoder($id);
        if (empty($decoded)) {
            return null;
        }

        return (int)$decoded[0];
    }

    public function skipHashIdDecode(null|array|string|int $field): bool
    {
        return $field === null || $field === '' || $field === [] || $field === 0 || $field === '0';
    }

    /**
     * Without decoding the encoded ID's you won't be able to use
     * validation features like `exists:table,id`.
     *
     * @throws IncorrectIdException
     * @throws \Throwable
     */
    protected function decodeHashedIdsBeforeValidation(array $requestData): array
    {
        // The hash ID feature must be enabled to use this decoder feature.
        if (property_exists($this, 'decode') && !empty($this->decode) && config('apiato.hash-id')) {
            // Iterate over each key (ID that needs to be decoded) and call keys locator to decode them
            foreach ($this->decode as $key) {
                $requestData = $this->locateAndDecodeIds($requestData, $key);
            }
        }

        return $requestData;
    }

    /**
     * @param string $id
     */
    private function decoder($id): array
    {
        return Hashids::decode($id);
    }

    /**
     * Search the IDs to be decoded in the request data.
     *
     * @throws IncorrectIdException
     * @throws \Throwable
     */
    private function locateAndDecodeIds(array $requestData, string $key): array
    {
        // Split the key based on the "."
        $fields = explode('.', $key);

        // Loop through all elements of the key.
        return (array)($this->processField($requestData, $fields, $key));
    }

    /**
     * Recursive function to process (decode) the request data with a given key.
     *
     * @return array|string|int|null
     *
     * @throws IncorrectIdException
     * @throws \Throwable
     */
    private function processField(null|array|string|int $data, ?array $keysTodo = null, ?string $currentFieldName = null): mixed
    {
        // Check if there are no more fields to be processed.
        if ($keysTodo === null || $keysTodo === []) {
            // There are no more keys left - so basically we need to decode this entry.
            if ($this->skipHashIdDecode($data)) {
                return $data;
            }

            throw_if(
                $data !== null && !\is_string($data),
                (new CoreInternalErrorException('String expected, got ' . gettype($data), 422))
                    ->withErrors([$currentFieldName => 'String expected, got ' . gettype($data)]),
            );

            $decodedField = $this->decode($data);

            if ($decodedField === null) {
                throw new IncorrectIdException(sprintf('ID (%s) is incorrect, consider using the hashed ID.', $currentFieldName));
            }

            return $decodedField;
        }

        // Take the first element from the field
        $field = array_shift($keysTodo);

        // Is the current field an array?! we need to process it like crazy
        if ($field === '*') {
            // Make sure field value is an array
            $data = \is_array($data) ? $data : [$data];

            // Process each field of the array (and go down one level!)
            $fields = $data;
            foreach ($fields as $key => $value) {
                $data[$key] = $this->processField($value, $keysTodo, sprintf('%s[%s]', $currentFieldName, $key));
            }

            return $data;
        }

        // Check if the key we are looking for does, in fact, really exist
        if (!\is_array($data) || !\array_key_exists($field, $data)) {
            return $data;
        }

        // Go down one level
        $value = $data[$field];
        $data[$field] = $this->processField($value, $keysTodo, $field);

        return $data;
    }
}
