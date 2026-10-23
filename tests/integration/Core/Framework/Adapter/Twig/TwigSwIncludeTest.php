<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Adapter\Twig;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Contena\Core\Framework\Adapter\Twig\TwigEnvironment;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Kernel;
use Contena\Core\Test\Stub\Framework\BundleFixture;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @internal
 */
class TwigSwIncludeTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMultipleInheritance(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Frontend', __DIR__ . '/fixtures/Frontend/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('frontend/frontend/index.html.twig'), 'frontend/frontend/index.html.twig');
        static::assertSame('innerblockplugin2innerblockplugin1innerblock', $template->render([]));
    }

    public function testInterpolatedInheritance(): void
    {
        // order frontend, TestPlugin2, TestPlugin1 is important for this test. Do not change.
        $twig = $this->initTwig([
            new BundleFixture('Frontend', __DIR__ . '/fixtures/Frontend/'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('@TestPlugin1/frontend/frontend/include_base.html.twig'), '@TestPlugin1/frontend/frontend/include_base.html.twig');
        static::assertSame('--(Textfield base)(Textfield Extend)-(Textfield base)(Textfield Extend)--', $template->render([]));
    }

    public function testIncludeWithVars(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Frontend', __DIR__ . '/fixtures/Frontend/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('frontend/frontend/withvars.html.twig'), 'frontend/frontend/withvars.html.twig');
        static::assertSame('innerblockvaluefromindex', $template->render([]));
    }

    public function testIncludeWithVarsOnly(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Frontend', __DIR__ . '/fixtures/Frontend/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('frontend/frontend/withvarsonly.html.twig'), 'frontend/frontend/withvarsonly.html.twig');
        static::assertSame('innerblockvaluefromindexnotvisibleinnerblockvaluefromindex', $template->render([]));
    }

    public function testIncludeTemplatenameExpression(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Frontend', __DIR__ . '/fixtures/Frontend/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('frontend/frontend/templatenameexpression.html.twig'), 'frontend/frontend/templatenameexpression.html.twig');
        static::assertSame('innerblockplugin2innerblockplugin1innerblock', $template->render([]));
    }

    public function testIncludeIgnoreMissing(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Frontend', __DIR__ . '/fixtures/Frontend/'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('frontend/frontend/notemplatefound.html.twig'), 'frontend/frontend/notemplatefound.html.twig');
        static::assertSame('nothingelse', $template->render([]));
    }

    public function testDynamicInclude(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Frontend', __DIR__ . '/fixtures/Frontend/'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('frontend/frontend/dynamic_include.html.twig'), 'frontend/frontend/dynamic_include.html.twig');
        static::assertSame('a', $template->render(['child' => 'a']));
        static::assertSame('b', $template->render(['child' => 'b']));
    }

    public function testDynamicIncludeExtended(): void
    {
        $twig = $this->initTwig([
            new BundleFixture('Frontend', __DIR__ . '/fixtures/Frontend/'),
            new BundleFixture('TestPlugin1', __DIR__ . '/fixtures/Plugins/TestPlugin1'),
            new BundleFixture('TestPlugin2', __DIR__ . '/fixtures/Plugins/TestPlugin2'),
        ]);

        $template = $twig->loadTemplate($twig->getTemplateClass('frontend/frontend/dynamic_include.html.twig'), 'frontend/frontend/dynamic_include.html.twig');
        static::assertSame('a/TestPlugin1_a/TestPlugin2_a', $template->render(['child' => 'a']));
        static::assertSame('b/TestPlugin1_b/TestPlugin2_b', $template->render(['child' => 'b']));
    }

    /**
     * @param BundleFixture[] $bundles
     */
    private function initTwig(array $bundles): Environment
    {
        $loader = new FilesystemLoader(__DIR__ . '/fixtures/Frontend/Resources/views');

        foreach ($bundles as $bundle) {
            $directory = $bundle->getPath() . '/Resources/views';
            $loader->addPath($directory);
            $loader->addPath($directory, $bundle->getName());
        }

        $twig = new TwigEnvironment($loader, ['cache' => false]);

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')
            ->willReturn($bundles);

        $scopeDetector = static::createStub(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')
            ->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            static::getContainer()->getParameter('kernel.cache_dir') . '/' . microtime(),
            new NamespaceHierarchyBuilder([
                new BundleHierarchyBuilder(
                    $kernel,
                    static::getContainer()->get(Connection::class)
                ),
            ]),
            $scopeDetector,
        );

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));
        $twig->getExtension(NodeExtension::class)->getFinder();

        return $twig;
    }
}
