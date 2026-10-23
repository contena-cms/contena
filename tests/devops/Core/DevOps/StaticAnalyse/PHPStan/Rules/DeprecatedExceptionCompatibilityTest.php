<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Order\Exception\PaymentMethodNotAvailableException;
use Contena\Core\Checkout\Promotion\Cart\Discount\Filter\Exception\FilterPickerNotFoundException;
use Contena\Core\Checkout\Promotion\Cart\Discount\Filter\Exception\FilterSorterNotFoundException;
use Contena\Core\Checkout\Promotion\Exception\DiscountCalculatorNotFoundException;
use Contena\Core\Checkout\Promotion\Exception\InvalidScopeDefinitionException;
use Contena\Core\Checkout\Promotion\Exception\PriceNotFoundException;
use Contena\Core\Content\Blog\Exception\ReviewNotActiveExeption;
use Contena\Core\Content\Cms\Exception\PageNotFoundException;
use Contena\Core\Content\Flow\Exception\CustomTriggerByNameNotFoundException;
use Contena\Core\Content\ImportExport\Exception\LogNotWritableException;
use Contena\Core\Content\ImportExport\Exception\MappingException;
use Contena\Core\Content\MailTemplate\Exception\ChannelNotFoundException;
use Contena\Core\Framework\Api\ApiDefinition\ApiDefinitionGeneratorNotFoundException;
use Contena\Core\Framework\Api\ApiDefinition\ApiTypeNotFoundException;
use Contena\Core\Framework\Api\Context\Exception\InvalidContextSourceUserException;
use Contena\Core\Framework\Api\Controller\Exception\AuthThrottledException;
use Contena\Core\Framework\Api\Exception\InvalidSyncOperationException;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Exception\InvalidSortingDirectionException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Exception\UnmappedFieldException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\AssociationNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InvalidPriceFieldTypeException;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Plugin\Exception\KernelPluginLoaderException;
use Contena\Core\Framework\Plugin\Exception\PluginComposerRemoveException;
use Contena\Core\Framework\Plugin\Exception\PluginComposerRequireException;
use Contena\Core\Framework\Plugin\Exception\PluginNotAZipFileException;
use Contena\Core\Framework\Rule\Exception\UnsupportedValueException;
use Contena\Core\Framework\Store\Exception\LicenseDomainVerificationException;
use Contena\Core\Framework\Util\Exception\ComparatorException;
use Contena\Core\System\Currency\CurrencyDefinition;
use Contena\Frontend\Framework\Media\Exception\MediaValidatorMissingException;
use Contena\Frontend\Theme\Exception\InvalidThemeBundleException;
use Contena\Frontend\Theme\Exception\ThemeAssignmentException;

/**
 * Exercises the removal contract across legacy exception classes, independently of domain callers.
 *
 * @internal
 */
class DeprecatedExceptionCompatibilityTest extends TestCase
{
    /**
     * @param class-string<ContenaHttpException> $class
     * @param list<mixed> $arguments
     */
    #[DataProvider('exceptionProvider')]
    public function testLegacyConstructionAndMetadataRemainUsable(string $class, array $arguments): void
    {
        $exception = Feature::fake([], static fn () => new $class(...$arguments));

        // Error formatting must remain safe even if a legacy object survives a flag-state change.
        Feature::fake(['v6.8.0.0'], static function () use ($exception): void {
            static::assertNotSame('', $exception->getErrorCode());
            static::assertGreaterThanOrEqual(400, $exception->getStatusCode());
            static::assertNotSame([], iterator_to_array($exception->getErrors()));
            if ($exception instanceof UnsupportedValueException) {
                static::assertSame('type', $exception->getType());
                static::assertSame('class', $exception->getClass());
            }
        });
    }

    public function testExistingExceptionMetadataRemainsCallableInMajorMode(): void
    {
        $exceptions = Feature::fake([], static fn () => [
            new LogNotWritableException(['id']),
            new MappingException(),
            new ChannelNotFoundException('id'),
            new AuthThrottledException(10),
            new ThemeAssignmentException('theme', [], [], []),
        ]);

        Feature::fake(['v6.8.0.0'], static function () use ($exceptions): void {
            foreach ($exceptions as $exception) {
                static::assertNotSame('', $exception->getErrorCode());
                static::assertGreaterThanOrEqual(400, $exception->getStatusCode());
                static::assertNotSame([], iterator_to_array($exception->getErrors()));
            }

            static::assertSame(10, $exceptions[3]->getWaitTime());
            static::assertSame([], $exceptions[4]->getAssignedChannels());
        });
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testComparatorFactoryThrowsInMajorMode(): void
    {
        static::expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(ComparatorException::class, ComparatorException::class . '::operatorNotSupported', 'v6.8.0.0')));

        Feature::fake(['v6.8.0.0'], static fn () => ComparatorException::operatorNotSupported('invalid'));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     *
     * @param class-string<ContenaHttpException> $class
     * @param list<mixed> $arguments
     */
    #[DataProvider('exceptionProvider')]
    public function testConstructionThrowsInMajorMode(string $class, array $arguments): void
    {
        static::expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedClassMessage($class, 'v6.8.0.0')));

        Feature::fake(['v6.8.0.0'], static fn () => new $class(...$arguments));
    }

    /**
     * @return iterable<string, array{class-string<ContenaHttpException>, list<mixed>}>
     */
    public static function exceptionProvider(): iterable
    {
        yield 'missing media validator' => [MediaValidatorMissingException::class, ['image']];
        yield 'invalid theme bundle' => [InvalidThemeBundleException::class, ['theme']];
        yield 'license verification' => [LicenseDomainVerificationException::class, ['domain']];
        yield 'invalid sync operation' => [InvalidSyncOperationException::class, ['invalid']];
        yield 'missing API type' => [ApiTypeNotFoundException::class, ['type']];
        yield 'missing API generator' => [ApiDefinitionGeneratorNotFoundException::class, ['format']];
        yield 'invalid context source' => [InvalidContextSourceUserException::class, ['source']];
        yield 'plugin require failure' => [PluginComposerRequireException::class, ['plugin', 'package', 'output']];
        yield 'plugin removal failure' => [PluginComposerRemoveException::class, ['plugin', 'package', 'output']];
        yield 'plugin loader failure' => [KernelPluginLoaderException::class, ['plugin', 'reason']];
        yield 'plugin is not a ZIP' => [PluginNotAZipFileException::class, ['mime']];
        yield 'unsupported rule value' => [UnsupportedValueException::class, ['type', 'class']];
        yield 'missing association' => [AssociationNotFoundException::class, ['association']];
        yield 'invalid price type' => [InvalidPriceFieldTypeException::class, ['type']];
        yield 'invalid sorting direction' => [InvalidSortingDirectionException::class, ['direction']];
        yield 'unmapped field' => [UnmappedFieldException::class, ['field', new CurrencyDefinition()]];
        yield 'comparator direct construction' => [ComparatorException::class, [400, 'code', 'message']];
        yield 'missing payment method' => [PaymentMethodNotAvailableException::class, ['id']];
        yield 'missing discount calculator' => [DiscountCalculatorNotFoundException::class, ['type']];
        yield 'missing price' => [PriceNotFoundException::class, [new LineItem('id', 'custom')]];
        yield 'invalid discount scope' => [InvalidScopeDefinitionException::class, ['scope']];
        yield 'missing filter picker' => [FilterPickerNotFoundException::class, ['key']];
        yield 'missing filter sorter' => [FilterSorterNotFoundException::class, ['key']];
        yield 'missing flow trigger' => [CustomTriggerByNameNotFoundException::class, ['event']];
        yield 'missing CMS page' => [PageNotFoundException::class, ['id']];
        yield 'inactive review' => [ReviewNotActiveExeption::class, []];
    }
}
