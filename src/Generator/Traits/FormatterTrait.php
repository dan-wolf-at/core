<?php

declare(strict_types=1);

namespace Apiato\Core\Generator\Traits;

use Illuminate\Support\Str;

trait FormatterTrait
{
    public function prependOperationToName(string $operation, string $class): string
    {
        $className = ('list' === $operation) ? Str::pluralStudly($class) : $class;

        return $operation . $this->capitalize($className);
    }

    public function capitalize(string $word): string
    {
        return ucfirst($word);
    }

    protected function trimString(string $string): string
    {
        return trim($string);
    }
}
