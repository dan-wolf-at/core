<?php

declare(strict_types=1);

namespace Apiato\Core\Abstracts\Collections;

use Illuminate\Database\Eloquent\Collection;

/**
 * @method static bool containsDecodedHash(string $hashedValue, string $key = 'id')
 */
abstract class EloquentCollection extends Collection
{
}
