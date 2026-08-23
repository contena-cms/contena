<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\StateMachineStateFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\DateDefinition;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(StateMachineStateFieldSerializer::class)]
class StateMachineStateFieldSerializerTest extends TestCase
{
    private StateMachineStateFieldSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new StateMachineStateFieldSerializer(
            Validation::createValidator(),
            static::createStub(DefinitionInstanceRegistry::class)
        );
    }

    public function testEncodeAllowsAnyStateWhenCreatingEntity(): void
    {
        $stateId = Uuid::randomHex();

        $encoded = $this->withScope(Context::USER_SCOPE, fn (WriteParameterBag $parameters) => iterator_to_array($this->serializer->encode(
            $this->createField(),
            new EntityExistence(null, [], false, false, false, []),
            new KeyValuePair('stateId', $stateId, true),
            $parameters
        )));

        static::assertSame(['state_id' => Uuid::fromHexToBytes($stateId)], $encoded);
    }

    public function testEncodeRejectsStateChangeWhenScopeIsNotAllowed(): void
    {
        $this->withScope(Context::USER_SCOPE, function (WriteParameterBag $parameters): void {
            try {
                iterator_to_array($this->serializer->encode(
                    $this->createField(),
                    new EntityExistence(null, [], true, false, false, []),
                    new KeyValuePair('stateId', Uuid::randomHex(), true),
                    $parameters
                ));

                static::fail(WriteConstraintViolationException::class . ' not thrown.');
            } catch (WriteConstraintViolationException $exception) {
                static::assertSame('/stateId', $exception->getViolations()->get(0)->getPropertyPath());
            }
        });
    }

    public function testEncodeAllowsStateChangeWhenScopeIsAllowed(): void
    {
        $stateId = Uuid::randomHex();

        $encoded = $this->withScope(Context::SYSTEM_SCOPE, fn (WriteParameterBag $parameters) => iterator_to_array($this->serializer->encode(
            $this->createField(),
            new EntityExistence(null, [], true, false, false, []),
            new KeyValuePair('stateId', $stateId, true),
            $parameters
        )));

        static::assertSame(['state_id' => Uuid::fromHexToBytes($stateId)], $encoded);
    }

    private function createField(): StateMachineStateField
    {
        return new StateMachineStateField('state_id', 'stateId', 'test.state');
    }

    /**
     * @template TReturn
     *
     * @param \Closure(WriteParameterBag): TReturn $callback
     *
     * @return TReturn
     */
    private function withScope(string $scope, \Closure $callback): mixed
    {
        $context = Context::createDefaultContext();

        return $context->scope($scope, fn (Context $scopedContext) => $callback(new WriteParameterBag(
            new DateDefinition(),
            WriteContext::createFromContext($scopedContext),
            '',
            new WriteCommandQueue()
        )));
    }
}
