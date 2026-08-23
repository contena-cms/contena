<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\Notification\NotificationDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Util\Hasher;
use Contena\Frontend\Theme\ThemeDefinition;

/**
 * @internal
 */
final class ApiAwareTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour;
    use KernelTestBehaviour;

    public function testApiAware(): void
    {
        $cacheId = Hasher::hashFile(__DIR__ . '/fixtures/api-aware-fields.json');

        $kernel = KernelLifecycleManager::createKernel(
            null,
            true,
            $cacheId
        );
        $kernel->boot();
        $registry = $kernel->getContainer()->get(DefinitionInstanceRegistry::class);

        $mapping = [];

        foreach ($registry->getDefinitions() as $definition) {
            foreach ($definition->getFields() as $field) {
                $flag = $field->getFlag(ApiAware::class);
                if ($flag === null || !$flag->isSourceAllowed(ChannelApiSource::class)) {
                    continue;
                }

                $mapping[] = $definition->getEntityName() . '.' . $field->getPropertyName();
            }
        }

        $expected = file_get_contents(__DIR__ . '/fixtures/api-aware-fields.json');
        if (!\is_string($expected)) {
            static::fail(__DIR__ . '/fixtures/api-aware-fields.json could not be read');
        }

        $expected = \json_decode($expected, true, flags: \JSON_THROW_ON_ERROR);

        if (static::getContainer()->has(ThemeDefinition::class)) {
            $expected = array_merge(
                $expected,
                [
                    'theme.id',
                    'theme.technicalName',
                    'theme.name',
                    'theme.author',
                    'theme.description',
                    'theme.customFields',
                    'theme.previewMediaId',
                    'theme.parentThemeId',
                    'theme.baseConfig',
                    'theme.configValues',
                    'theme.active',
                    'theme.media',
                    'theme.createdAt',
                    'theme.updatedAt',
                    'theme.translated',
                    'theme_translation.description',
                    'theme_translation.customFields',
                    'theme_translation.createdAt',
                    'theme_translation.updatedAt',
                    'theme_translation.themeId',
                    'theme_translation.languageId',
                ]
            );
        }

        if (static::getContainer()->has(NotificationDefinition::class)) {
            $expected = array_merge(
                $expected,
                [
                    'notification.createdAt',
                    'notification.updatedAt',
                ]
            );
        }

        $message = 'One or more fields have been changed in their visibility for the Channel API. '
            . 'This change must be carefully controlled to ensure that no sensitive data is given out via the Channel API.';

        static::assertSame([], array_diff($mapping, $expected), $message);
        static::assertSame([], array_diff($expected, $mapping), $message);
    }
}
