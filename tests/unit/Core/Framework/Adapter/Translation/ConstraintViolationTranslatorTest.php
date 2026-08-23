<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Translation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Translation\ConstraintViolationTranslator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(ConstraintViolationTranslator::class)]
class ConstraintViolationTranslatorTest extends TestCase
{
    public function testTranslatesCustomMessageTemplate(): void
    {
        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('This email address is not allowed.');

        $translator = new ConstraintViolationTranslator($translator);

        $message = $translator->translate(new ConstraintViolation(
            'error.urlNotAllowed',
            'error.urlNotAllowed',
            [],
            null,
            'email',
            null,
        ));

        static::assertSame('This email address is not allowed.', $message);
    }

    public function testTranslatesViolationCodeWhenMessageTemplateHasNoTranslation(): void
    {
        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id): string => $id === 'error.VIOLATION::TEST_ERROR' ? 'This email address is invalid.' : $id
        );

        $translator = new ConstraintViolationTranslator($translator);

        $message = $translator->translate(new ConstraintViolation(
            'This value is not valid.',
            'This value is not valid.',
            [],
            null,
            'email',
            null,
            null,
            'VIOLATION::TEST_ERROR',
        ));

        static::assertSame('This email address is invalid.', $message);
    }

    public function testFallsBackToSymfonyViolationMessageWhenNoTranslationIsAvailable(): void
    {
        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        $translator = new ConstraintViolationTranslator($translator);

        $message = $translator->translate(new ConstraintViolation(
            'This value is not valid.',
            'This value is not valid.',
            [],
            null,
            'email',
            null,
            null,
            'VIOLATION::MISSING_TRANSLATION',
        ));

        static::assertSame('This value is not valid.', $message);
    }
}
