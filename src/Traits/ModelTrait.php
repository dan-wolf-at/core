<?php

declare(strict_types=1);

namespace Apiato\Core\Traits;

use Illuminate\Database\Eloquent\Factories\HasFactory;

trait ModelTrait
{
    use CanOwnTrait;
    use FactoryLocatorTrait, HasFactory {
        FactoryLocatorTrait::newFactory insteadof HasFactory;
    }
    use HashIdTrait;
    use HashedRouteBindingTrait;
    use HasResourceKeyTrait;
}
