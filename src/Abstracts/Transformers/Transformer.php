<?php

declare(strict_types=1);

namespace Apiato\Core\Abstracts\Transformers;

use Apiato\Core\Exceptions\CoreInternalErrorException;
use Apiato\Core\Exceptions\UnsupportedFractalIncludeException;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Primitive;
use League\Fractal\Scope;
use League\Fractal\TransformerAbstract as FractalTransformer;

abstract class Transformer extends FractalTransformer
{
    public function nullableItem($data, $transformer, ?string $resourceKey = null): Primitive|Item
    {
        if (is_null($data)) {
            return $this->primitive(null);
        }

        return $this->item($data, $transformer, $resourceKey);
    }

    #[\Override]
    public function item($data, $transformer, ?string $resourceKey = null): Item
    {
        // set a default resource key if none is set
        if (($resourceKey === null || $resourceKey === '' || $resourceKey === '0') && $data) {
            $resourceKey = $data->getResourceKey();
        }

        return parent::item($data, $transformer, $resourceKey);
    }

    #[\Override]
    public function collection($data, $transformer, ?string $resourceKey = null): Collection
    {
        // set a default resource key if none is set
        if (($resourceKey === null || $resourceKey === '' || $resourceKey === '0') && $data->isNotEmpty()) {
            $resourceKey = $data->first()->getResourceKey();
        }

        return parent::collection($data, $transformer, $resourceKey);
    }

    #[\Override]
    protected function callIncludeMethod(Scope $scope, string $includeName, $data)
    {
        try {
            return parent::callIncludeMethod($scope, $includeName, $data);
        } catch (\ErrorException $exception) {
            if (config('apiato.requests.force-valid-includes', true)) {
                throw new UnsupportedFractalIncludeException($exception->getMessage());
            }
        } catch (\Exception $exception) {
            throw new CoreInternalErrorException($exception->getMessage());
        }

        return null;
    }

    public static function empty(): callable
    {
        return static function (): array {
            return [];
        };
    }
}
