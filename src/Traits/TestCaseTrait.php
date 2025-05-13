<?php

declare(strict_types=1);

namespace Apiato\Core\Traits;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated(reason: 'This trait is complicated a lot stuff that can be done in a much simpler way.')]
trait TestCaseTrait
{
    /**
     * Override default URL subDomain in case you want to change it for some tests.
     */
    public function overrideSubDomain($url = null): ?string
    {
        // `subDomain` is a property defined in your class.
        if (!property_exists($this, 'subDomain')) {
            return null;
        }

        $url = ($url) ?: $this->baseUrl;

        $info = parse_url((string) $url);

        $array = explode('.', $info['host']);

        $withoutDomain = (\array_key_exists(
            \count($array) - 2,
            $array,
        ) ? $array[\count($array) - 2] : '') . '.' . $array[\count($array) - 1];

        $newSubDomain = $info['scheme'] . '://' . $this->subDomain . '.' . $withoutDomain;

        return $this->baseUrl = $newSubDomain;
    }
}
