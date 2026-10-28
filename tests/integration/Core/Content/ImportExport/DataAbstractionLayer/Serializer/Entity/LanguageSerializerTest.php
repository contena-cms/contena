<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\LanguageSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageDefinition;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class LanguageSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<LanguageCollection>
     */
    private EntityRepository $languageRepository;

    private LanguageSerializer $serializer;

    private string $languageId = '1a9e90835a634ffd900b5a441251f551';

    protected function setUp(): void
    {
        $this->languageRepository = static::getContainer()->get('language.repository');
        $serializerRegistry = static::getContainer()->get(SerializerRegistry::class);

        $this->serializer = new LanguageSerializer($this->languageRepository);
        $this->serializer->setRegistry($serializerRegistry);
    }

    public function testSimple(): void
    {
        $localeId = Uuid::randomHex();
        $this->createCountry($localeId);

        $config = new Config([], [], []);
        $language = [
            'active' => true,
            'locale' => [
                'code' => 'de-DE-1',
                'id' => $localeId,
            ],
        ];

        $serialized = iterator_to_array($this->serializer->serialize($config, $this->languageRepository->getDefinition(), $language));

        $deserialized = iterator_to_array($this->serializer->deserialize($config, $this->languageRepository->getDefinition(), $serialized));

        static::assertSame($this->languageId, $deserialized['id']);
        static::assertSame($localeId, $deserialized['locale']['id']);
    }

    public function testSupportsOnlyCountry(): void
    {
        $serializer = new LanguageSerializer(static::getContainer()->get('language.repository'));

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();

            if ($entity === LanguageDefinition::ENTITY_NAME) {
                static::assertTrue($serializer->supports($entity));
            } else {
                static::assertFalse(
                    $serializer->supports($entity),
                    LanguageDefinition::class . ' should not support ' . $entity
                );
            }
        }
    }

    private function createCountry(string $localeId): void
    {
        $this->languageRepository->upsert([
            [
                'id' => $this->languageId,
                'name' => 'test name',
                'active' => true,
                'locale' => [
                    'id' => $localeId,
                    'code' => 'de-DE-1',
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
                'translationCodeId' => $localeId,
            ],
        ], Context::createDefaultContext());
    }
}
