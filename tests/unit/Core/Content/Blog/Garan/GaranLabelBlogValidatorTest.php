<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Garan\GaranLabelBlogValidator;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(GaranLabelBlogValidator::class)]
class GaranLabelBlogValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    private GaranLabelBlogValidator $validator;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [BlogDefinition::class, TaxDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $this->validator = new GaranLabelBlogValidator();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = GaranLabelBlogValidator::getSubscribedEvents();

        static::assertCount(1, $events);
        static::assertSame('validate', $events[PreWriteValidationEvent::class]);
    }

    #[TestWith([30, true])]
    #[TestWith([36, true])]
    #[TestWith([600, true])]
    #[TestWith([24, false])]
    #[TestWith([606, false])]
    #[TestWith([33, false])]
    #[TestWith([0, false])]
    #[TestWith([-36, false])]
    public function testIsValidDuration(int $months, bool $expected): void
    {
        static::assertSame($expected, GaranLabelBlogValidator::isValidDuration($months));
    }

    public function testIgnoresNonBlogEntities(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('tax'), ['id' => $id, 'guarantee_months' => 1], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresPayloadWithoutGuaranteeMonthsKey(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('blog'), ['id' => $id], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresNullGuaranteeMonths(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('blog'), ['id' => $id, 'guarantee_months' => null], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[TestWith([36])]
    #[TestWith([48])]
    #[TestWith([30])]
    #[TestWith([600])]
    public function testAllowsValidGuaranteeMonths(int $months): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('blog'), ['id' => $id, 'guarantee_months' => $months], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testCatchesInsertWithTooLowDuration(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('blog'), ['id' => $id, 'guarantee_months' => 24], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());

        $violation = $exception->getViolations()->get(0);
        static::assertSame(GaranLabelBlogValidator::VIOLATION_CODE, $violation->getCode());
        static::assertSame('/insert/guaranteeMonths', $violation->getPropertyPath());
    }

    public function testCatchesInsertWithTooHighDuration(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('blog'), ['id' => $id, 'guarantee_months' => 606], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());

        $violation = $exception->getViolations()->get(0);
        static::assertSame(GaranLabelBlogValidator::VIOLATION_CODE, $violation->getCode());
        static::assertSame('/insert/guaranteeMonths', $violation->getPropertyPath());
    }

    public function testCatchesInsertWithNonMultipleOfSix(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('blog'), ['id' => $id, 'guarantee_months' => 37], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());

        $violation = $exception->getViolations()->get(0);
        static::assertSame(GaranLabelBlogValidator::VIOLATION_CODE, $violation->getCode());
    }

    public function testCatchesNonIntegerValue(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('blog'), ['id' => $id, 'guarantee_months' => '36'], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());

        $violation = $exception->getViolations()->get(0);
        static::assertSame(GaranLabelBlogValidator::VIOLATION_CODE, $violation->getCode());
    }

    public function testCatchesUpdateWithInvalidDuration(): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new UpdateCommand($this->registry->getByEntityName('blog'), ['id' => $id, 'guarantee_months' => 12], ['id' => $id], static::createStub(EntityExistence::class), '/update'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());

        $violation = $exception->getViolations()->get(0);
        static::assertSame('/update/guaranteeMonths', $violation->getPropertyPath());
    }

    #[TestWith(['https://example.com/guarantee-terms.pdf'])]
    #[TestWith(['HTTP://example.com'])]
    #[TestWith([null])]
    public function testAllowsWebGuaranteeTermsUrls(?string $url): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('blog'), ['id' => $id, 'guarantee_terms_url' => $url], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[TestWith(['javascript:alert(1)'])]
    #[TestWith(['data:text/html,<script>alert(1)</script>'])]
    #[TestWith(['www.example.com'])]
    #[TestWith(['ftp://example.com'])]
    #[TestWith(['https://'])]
    #[TestWith(['https://example.com/terms pdf'])]
    #[TestWith(["https://example.com\n"])]
    public function testCatchesGuaranteeTermsUrlsThatAreNoWebUrls(string $url): void
    {
        $id = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand($this->registry->getByEntityName('blog'), ['id' => $id, 'guarantee_terms_url' => $url], ['id' => $id], static::createStub(EntityExistence::class), '/insert'),
            ]
        );

        $this->validator->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());

        $violation = $exception->getViolations()->get(0);
        static::assertSame(GaranLabelBlogValidator::TERMS_URL_VIOLATION_CODE, $violation->getCode());
        static::assertSame('/insert/guaranteeTermsUrl', $violation->getPropertyPath());
    }
}
