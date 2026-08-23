<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo;

use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\Slugify;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Seo\SeoUrlGenerator;
use Contena\Core\Content\Seo\SeoUrlTwigFactory;
use Contena\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Contena\Core\Framework\Adapter\Twig\SecurityExtension;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Twig\Cache\FilesystemCache;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(SeoUrlTwigFactory::class)]
class SeoUrlTwigFactoryTest extends TestCase
{
    public function testCreateTwigEnvironment(): void
    {
        $fs = (new Filesystem());
        $tmpDir = Path::join(sys_get_temp_dir(), uniqid('twig-cache', false));
        $fs->mkdir($tmpDir);

        $slugify = new Slugify();
        $factory = new SeoUrlTwigFactory();
        $twig = $factory->createTwigEnvironment($slugify, [
            new SlugifyExtension($slugify),
            new PhpSyntaxExtension(),
            new SecurityExtension([]),
        ], $tmpDir);

        static::assertTrue($twig->hasExtension(SlugifyExtension::class));
        static::assertTrue($twig->hasExtension(PhpSyntaxExtension::class));
        static::assertTrue($twig->hasExtension(SecurityExtension::class));
        static::assertInstanceOf(ArrayLoader::class, $twig->getLoader());
        static::assertTrue($twig->isStrictVariables());
        static::assertInstanceOf(FilesystemCache::class, $twig->getCache());

        $template = '{% autoescape \'' . SeoUrlGenerator::ESCAPE_SLUGIFY . '\' %}{{ blog.name }}{% endautoescape %}';
        $template = $twig->createTemplate($template);
        static::assertSame('hello-world', $template->render(['blog' => ['name' => 'hello world']]));

        $template = '{% autoescape \'' . SeoUrlGenerator::ESCAPE_SLUGIFY . '\' %}{{ blog.name }}{% endautoescape %}';
        $template = $twig->createTemplate($template);
        static::assertSame('1-2024', $template->render(['blog' => ['name' => 01.2024]]));

        $template = '{% autoescape \'' . SeoUrlGenerator::ESCAPE_SLUGIFY . '\' %}{{ blog.name }}{% endautoescape %}';
        $template = $twig->createTemplate($template);
        static::assertSame('hello-01-2024', $template->render(['blog' => ['name' => 'Hello 01.2024']]));

        $fs->remove($tmpDir);
    }
}
