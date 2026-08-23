<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Installer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Installer\Installer;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(Installer::class)]
class InstallerTest extends TestCase
{
    private ContainerBuilder $container;

    /**
     * @var array<string, array{id:string, label:string}>
     */
    private array $supportedLanguages;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->registerExtension(new FrameworkExtension());

        $installer = new Installer();
        $installer->build($this->container);

        $supportedLanguages = $this->container->getParameter('contena.installer.supportedLanguages');
        static::assertIsArray($supportedLanguages);
        static::assertArrayHasKey('de', $supportedLanguages);
        $germanLanguage = $supportedLanguages['de'];
        static::assertArrayHasKey('id', $germanLanguage);
        static::assertArrayHasKey('label', $germanLanguage);
        $this->supportedLanguages = $supportedLanguages;
    }

    public function testSupportedLanguages(): void
    {
        static::assertSame(
            [
                'cs' => ['id' => 'cs-CZ', 'label' => 'Čeština'],
                'da-DK' => ['id' => 'da-DK', 'label' => 'Dansk'],
                'de' => ['id' => 'de-DE', 'label' => 'Deutsch'],
                'en-US' => ['id' => 'en-US', 'label' => 'English (US)'],
                'en' => ['id' => 'en-GB', 'label' => 'English (UK)'],
                'es-ES' => ['id' => 'es-ES', 'label' => 'Español'],
                'fr' => ['id' => 'fr-FR', 'label' => 'Français'],
                'it' => ['id' => 'it-IT', 'label' => 'Italiano'],
                'nl' => ['id' => 'nl-NL', 'label' => 'Nederlands'],
                'no' => ['id' => 'nn-NO', 'label' => 'Norsk'],
                'pl' => ['id' => 'pl-PL', 'label' => 'Język polski'],
                'pt-PT' => ['id' => 'pt-PT', 'label' => 'Português'],
                'sv-SE' => ['id' => 'sv-SE', 'label' => 'Svenska'],
            ],
            $this->supportedLanguages
        );
    }
}
