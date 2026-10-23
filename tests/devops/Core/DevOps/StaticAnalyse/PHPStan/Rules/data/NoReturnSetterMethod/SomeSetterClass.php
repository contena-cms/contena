<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoReturnSetterMethod;

use Contena\Core\Content\Blog\BlogEntity;

/**
 * @internal
 */
final class SomeSetterClass
{
    public function setName(string $name)
    {
        return 100;
    }

    public function setWithReturnType(string $name): object
    {
        return new \stdClass();
    }

    public function setWithObjectReturnType(string $name): BlogEntity
    {
        return new BlogEntity();
    }
}
