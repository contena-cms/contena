<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\App\Command;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\Command\UninstallAppCommand;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Console\Tester\CommandTester;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class UninstallAppCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');
    }

    public function testUninstall(): void
    {
        $this->appRepository->create([[
            'name' => 'SwagApp',
            'path' => __DIR__ . '/_fixtures/withPermissions',
            'version' => '0.9.0',
            'label' => 'test',
            'accessToken' => 'test',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'test',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => 'SwagApp',
            ],
        ]], Context::createDefaultContext());

        $commandTester = new CommandTester(static::getContainer()->get(UninstallAppCommand::class));

        $commandTester->execute(['name' => 'SwagApp']);

        static::assertSame(0, $commandTester->getStatusCode());

        static::assertStringContainsString('[OK] App uninstalled successfully.', $commandTester->getDisplay());
    }

    public function testUninstallWithNotFoundApp(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(UninstallAppCommand::class));

        $commandTester->execute(['name' => 'SwagApp']);

        static::assertSame(1, $commandTester->getStatusCode());

        static::assertStringContainsString('[ERROR] No app with name "SwagApp" installed.', $commandTester->getDisplay());
    }
}
