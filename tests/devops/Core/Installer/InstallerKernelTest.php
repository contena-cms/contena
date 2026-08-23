<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\Installer;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Contena\Core\Installer\Controller\SystemConfigurationController;
use Contena\Core\Installer\Installer;
use Contena\Core\Installer\InstallerKernel;
use Contena\Core\TestBootstrapper;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

/**
 * @internal
 */
class InstallerKernelTest extends TestCase
{
    use EnvTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setEnvVars(['COMPOSER_HOME' => null]);
    }

    #[TestDox('boot configures container with contena version and bundles')]
    public function testItCorrectlyConfiguresTheContainer(): void
    {
        $kernel = new InstallerKernel('test', false);
        $kernel->boot();
        static::assertTrue($kernel->getContainer()->hasParameter('kernel.contena_version'));

        // the default revision changes per commit, if it is set we expect that it is correct
        static::assertTrue($kernel->getContainer()->hasParameter('kernel.contena_version_revision'));

        static::assertSame(
            [
                'FrameworkBundle' => FrameworkBundle::class,
                'TwigBundle' => TwigBundle::class,
                'Installer' => Installer::class,
            ],
            $kernel->getContainer()->getParameter('kernel.bundles')
        );

        $configurationRoute = $kernel->getContainer()->get('router')->getRouteCollection()->get('installer.configuration');

        static::assertNotNull($configurationRoute);
        static::assertSame('/installer/configuration', $configurationRoute->getPath());
        static::assertSame(
            SystemConfigurationController::class . '::systemConfiguration',
            $configurationRoute->getDefault('_controller')
        );
    }

    #[TestDox('boot sets project dir and COMPOSER_HOME fallback')]
    public function testItCorrectlyConfiguresProjectDir(): void
    {
        $kernel = new InstallerKernel('test', false);
        $kernel->boot();
        $projectDir = new TestBootstrapper()->getProjectDir();

        static::assertSame($projectDir, $kernel->getContainer()->getParameter('kernel.project_dir'));
        static::assertSame($projectDir . '/var/cache/composer', EnvironmentHelper::getVariable('COMPOSER_HOME'));
    }
}
