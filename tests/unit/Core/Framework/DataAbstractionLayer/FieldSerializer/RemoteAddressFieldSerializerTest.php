<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\RemoteAddressFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(RemoteAddressFieldSerializer::class)]
class RemoteAddressFieldSerializerTest extends TestCase
{
    private RemoteAddressFieldSerializer $serializer;

    private Stub&SystemConfigService $configService;

    protected function setUp(): void
    {
        $this->configService = static::createStub(SystemConfigService::class);
        $this->serializer = new RemoteAddressFieldSerializer(
            Validation::createValidator(),
            static::createStub(DefinitionInstanceRegistry::class),
            $this->configService
        );
    }

    public function testEncodeRejectsInvalidField(): void
    {
        $field = new IntField('remote_address', 'remoteAddress');

        static::expectExceptionObject(DataAbstractionLayerException::invalidSerializerField(RemoteAddressField::class, $field));

        iterator_to_array($this->serializer->encode(
            $field,
            EntityExistence::createEmpty(),
            new KeyValuePair('remoteAddress', null, false),
            $this->createWriteParameterBag()
        ));
    }

    public function testEncodeAnonymizesAddressByDefault(): void
    {
        $this->configService->method('get')->willReturn(false);

        $encoded = iterator_to_array($this->serializer->encode(
            new RemoteAddressField('remote_address', 'remoteAddress'),
            EntityExistence::createEmpty(),
            new KeyValuePair('remoteAddress', '127.0.0.1', false),
            $this->createWriteParameterBag()
        ));

        static::assertSame(['remote_address' => IpUtils::anonymize('127.0.0.1')], $encoded);
    }

    public function testEncodeKeepsAddressWhenConfigured(): void
    {
        $this->configService->method('get')->willReturn(true);

        $encoded = iterator_to_array($this->serializer->encode(
            new RemoteAddressField('remote_address', 'remoteAddress'),
            EntityExistence::createEmpty(),
            new KeyValuePair('remoteAddress', '127.0.0.1', false),
            $this->createWriteParameterBag()
        ));

        static::assertSame(['remote_address' => '127.0.0.1'], $encoded);
    }

    public function testEncodeSkipsEmptyAddress(): void
    {
        $encoded = iterator_to_array($this->serializer->encode(
            new RemoteAddressField('remote_address', 'remoteAddress'),
            EntityExistence::createEmpty(),
            new KeyValuePair('remoteAddress', null, false),
            $this->createWriteParameterBag()
        ));

        static::assertSame([], $encoded);
    }

    private function createWriteParameterBag(): WriteParameterBag
    {
        return new WriteParameterBag(
            new DateDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }
}
