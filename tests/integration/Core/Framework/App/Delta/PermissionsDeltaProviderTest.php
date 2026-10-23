<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App\Delta;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\Delta\PermissionsDeltaProvider;
use Contena\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Contena\Core\Framework\App\Lifecycle\AppLifecycle;
use Contena\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Contena\Core\Framework\App\Manifest\Manifest;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @internal
 */
class PermissionsDeltaProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetName(): void
    {
        static::assertSame('permissions', (new PermissionsDeltaProvider())->getDeltaName());
    }

    public function testGetPermissionsDelta(): void
    {
        $context = Context::createDefaultContext();
        $manifest = $this->getTestManifest();

        $this->getAppLifecycle()->install($manifest, new AppInstallParameters(activate: false), $context);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('name', 'test'))
            ->addAssociation('acl_role');

        $app = $this->getAppRepository()->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($app);

        static::assertNotNull($app->getAclRole());

        // Modify the existing privileges to get a diff
        $app->getAclRole()->setPrivileges(['user:read']);

        $diff = (new PermissionsDeltaProvider())->getReport($manifest, $app);

        static::assertCount(6, $diff);
        static::assertArrayHasKey('category', $diff);
        static::assertArrayHasKey('custom_fields', $diff);
        static::assertArrayHasKey('order', $diff);
        static::assertArrayHasKey('blog', $diff);
        static::assertArrayHasKey('settings', $diff);
        static::assertArrayHasKey('additional_privileges', $diff);
    }

    public function testHasDelta(): void
    {
        $context = Context::createDefaultContext();
        $manifest = $this->getTestManifest();

        $this->getAppLifecycle()->install($manifest, new AppInstallParameters(activate: false), $context);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('name', 'test'))
            ->addAssociation('acl_role');

        $app = $this->getAppRepository()->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($app);

        $hasDelta = (new PermissionsDeltaProvider())->hasDelta($manifest, $app);

        static::assertFalse($hasDelta);
    }

    private function getAppLifecycle(): AbstractAppLifecycle
    {
        return static::getContainer()->get(AppLifecycle::class);
    }

    /**
     * @return EntityRepository<AppCollection>
     */
    private function getAppRepository(): EntityRepository
    {
        return static::getContainer()->get('app.repository');
    }

    private function getTestManifest(): Manifest
    {
        return Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
    }
}
