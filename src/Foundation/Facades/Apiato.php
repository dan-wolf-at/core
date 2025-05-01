<?php

declare(strict_types=1);

namespace Apiato\Core\Foundation\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array  getShipFoldersNames()
 * @method static array  getShipPath()
 * @method static array  getSectionContainerNames(string $sectionName)
 * @method static mixed getClassObjectFromFile(string $filePathName)
 * @method static string getClassFullNameFromFile(string $filePathName)
 * @method static array  getSectionPaths()
 * @method static string getClassType(string $className)
 * @method static array  getAllContainerNames()
 * @method static array  getAllContainerPaths()
 * @method static array  getSectionNames()
 * @method static array  getSectionContainerPaths(string $sectionName)
 * @method static void   verifyClassExist(string $className)
 *
 * @see \Apiato\Core\Foundation\Apiato
 */
class Apiato extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'Apiato';
    }
}
