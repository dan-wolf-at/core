<?php

declare(strict_types=1);

namespace Apiato\Core\Abstracts\Transformers;

use Apiato\Core\Exceptions\CoreInternalErrorException;
use Apiato\Core\Exceptions\UnsupportedFractalIncludeException;
use ErrorException;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Primitive;
use League\Fractal\Resource\ResourceInterface;
use League\Fractal\Scope;
use League\Fractal\TransformerAbstract as FractalTransformer;
use Throwable;

abstract class Transformer extends FractalTransformer
{
    /**
     * @param callable|FractalTransformer $transformer
     */
    public function nullableItem($data, $transformer, ?string $resourceKey = null): Primitive|Item
    {
        if (\is_null($data)) {
            return $this->primitive(null);
        }

        return $this->item($data, $transformer, $resourceKey);
    }

    /**
     * @param callable|FractalTransformer $transformer
     */
    #[\Override]
    public function item($data, $transformer, ?string $resourceKey = null): Item
    {
        // Set a default resource key if none is set
        if (($resourceKey === null || $resourceKey === '') && $data) {
            $resourceKey = $data->getResourceKey();
        }

        return parent::item($data, $transformer, $resourceKey);
    }

    /**
     * @param callable|FractalTransformer $transformer
     */
    #[\Override]
    public function collection($data, $transformer, ?string $resourceKey = null): Collection
    {
        // Set a default resource key if none is set
        if (($resourceKey === null || $resourceKey === '') && $data->isNotEmpty()) {
            $resourceKey = (string)$data->first()->getResourceKey();
        }

        return parent::collection($data, $transformer, $resourceKey);
    }

    public static function empty(): callable
    {
        return static function (): array {
            return [];
        };
    }

    /**
     * @throws CoreInternalErrorException
     * @throws UnsupportedFractalIncludeException
     */
    #[\Override]
    protected function callIncludeMethod(Scope $scope, string $includeName, $data): ResourceInterface|bool
    {
        try {
            return parent::callIncludeMethod($scope, $includeName, $data);
        } catch (Throwable $throwable) {
            if (
                $throwable instanceof ErrorException &&
                config('apiato.requests.force-valid-includes', true)
            ) {
                throw new UnsupportedFractalIncludeException($throwable->getMessage());
            }

            throw new CoreInternalErrorException($throwable->getMessage());
        }
    }
}
