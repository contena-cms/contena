<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Frontend\Framework\Routing\AbstractDomainLoader;

/**
 * @internal
 */
#[CoversClass(AbstractDomainLoader::class)]
class AbstractDomainLoaderTest extends TestCase
{
    public function testLoadDomainsThrowsWhenFeatureIsActive(): void
    {
        $loader = new AbstractDomainLoaderTestStub();

        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: Relying on the default implementation of %s::loadDomains() is deprecated. Implement loadDomains() in %s.',
            AbstractDomainLoader::class,
            $loader::class
        )));

        $loader->loadDomains();
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLoadDomainsDelegatesToLoadBeforeFeatureIsActive(): void
    {
        $loader = new AbstractDomainLoaderTestStub();

        $domains = $loader->loadDomains();

        static::assertCount(0, $domains);
        static::assertTrue($loader->loaded);
    }
}

/**
 * @internal
 */
class AbstractDomainLoaderTestStub extends AbstractDomainLoader
{
    public bool $loaded = false;

    public function getDecorated(): AbstractDomainLoader
    {
        return $this;
    }

    public function load(): array
    {
        $this->loaded = true;

        return [];
    }
}
