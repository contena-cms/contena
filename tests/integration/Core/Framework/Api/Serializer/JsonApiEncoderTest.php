<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Serializer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Serializer\JsonApiEncoder;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldPlainTestDefinition;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures\SerializationFixture;

/**
 * @internal
 */
class JsonApiEncoderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function emptyInputProvider(): iterable
    {
        yield 'empty input null' => [null];
        yield 'empty input string' => ['string'];
        yield 'empty input integer' => [1];
        yield 'empty input false' => [false];
        yield 'empty input date time' => [new \DateTime()];
        yield 'empty input float' => [1.1];
    }

    #[DataProvider('emptyInputProvider')]
    public function testEncodeWithEmptyInput(mixed $input): void
    {
        $this->expectExceptionObject(ApiException::unsupportedEncoderInput());

        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $encoder->encode(new Criteria(), static::getContainer()->get(BlogDefinition::class), $input, SerializationFixture::API_BASE_URL);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<mixed>|null $output
     */
    #[DataProvider('customFieldsProvider')]
    public function testCustomFields(array $input, ?array $output): void
    {
        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $definition = new CustomFieldPlainTestDefinition();
        $definition->compile(static::getContainer()->get(DefinitionInstanceRegistry::class));

        $struct = new class extends Entity {
            use EntityCustomFieldsTrait;
        };
        $struct->setUniqueIdentifier(Uuid::randomHex());
        $struct->assign($input);

        $actual = json_decode((string) $encoder->encode(new Criteria(), $definition, $struct, SerializationFixture::API_BASE_URL), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($output, $actual['data']['attributes']['customFields']);
    }

    /**
     * @return \Generator<string, array{array<string, mixed>, array<mixed>|null}>
     */
    public static function customFieldsProvider(): \Generator
    {
        yield 'Custom field null' => [['customFields' => null], null];
        yield 'Custom field with empty array' => [['customFields' => []], []];
        yield 'Custom field with values' => [['customFields' => ['bla']], ['bla']];
    }
}
