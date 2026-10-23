<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\LandingPage\Aggregate\LandingPageChannel\LandingPageChannelDefinition;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\LandingPage\LandingPageValidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(LandingPageValidator::class)]
class LandingPageValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [LandingPageDefinition::class, LandingPageChannelDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
    }

    public function testRejectsLandingPageWithoutAssignedChannel(): void
    {
        $event = $this->createEvent([
            $this->createInsert(LandingPageDefinition::ENTITY_NAME, ['id' => Uuid::randomBytes()], '/landing-page'),
        ]);

        (new LandingPageValidator(Validation::createValidator()))->preValidate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('/landing-page/channels', $exception->getViolations()->get(0)->getPropertyPath());
    }

    public function testAcceptsLandingPageWithAssignedChannel(): void
    {
        $landingPageId = Uuid::randomBytes();
        $event = $this->createEvent([
            $this->createInsert(LandingPageDefinition::ENTITY_NAME, ['id' => $landingPageId], '/landing-page'),
            $this->createInsert(LandingPageChannelDefinition::ENTITY_NAME, [
                'landing_page_id' => $landingPageId,
                'channel_id' => Uuid::randomBytes(),
            ], '/landing-page/sales-channels'),
        ]);

        (new LandingPageValidator(Validation::createValidator()))->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    /**
     * @param list<InsertCommand> $commands
     */
    private function createEvent(array $commands): PostWriteValidationEvent
    {
        return new PostWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            $commands,
        );
    }

    /**
     * @param array<string, string> $primaryKey
     */
    private function createInsert(string $entityName, array $primaryKey, string $path): InsertCommand
    {
        return new InsertCommand(
            $this->definitionRegistry->getByEntityName($entityName),
            [],
            $primaryKey,
            EntityExistence::createEmpty(),
            $path,
        );
    }
}
