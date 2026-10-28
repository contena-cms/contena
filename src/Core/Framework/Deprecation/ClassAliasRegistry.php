<?php declare(strict_types=1);

namespace Contena\Core\Framework\Deprecation;

use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity;
use Contena\Core\Checkout\DocumentV2\DocumentCollection;
use Contena\Core\Checkout\DocumentV2\DocumentDefinition;
use Contena\Core\Checkout\DocumentV2\DocumentEntity;
use Contena\Core\Checkout\DocumentV2\SalesChannel\AbstractDocumentRoute;
use Contena\Core\Checkout\DocumentV2\SalesChannel\DocumentRoute;
use Contena\Core\Checkout\DocumentV2\Service\ReferenceInvoiceLoader;
use Contena\Core\Checkout\DocumentV2\Struct\RenderedDocument;
use Contena\Core\Framework\Adapter\Asset\AssetService;
use Contena\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Notification\Api\NotificationController;
use Contena\Core\Framework\Notification\NotificationCollection;
use Contena\Core\Framework\Notification\NotificationDefinition;
use Contena\Core\Framework\Notification\NotificationEntity;

/**
 * @internal
 */
#[Package('framework')]
final class ClassAliasRegistry
{
    /**
     * The keys cannot be class-string because the legacy classes intentionally have no declarations.
     *
     * @var array<non-empty-string, class-string>
     */
    public const ALIASES = [
        'Contena\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection' => DocumentBaseConfigCollection::class,
        'Contena\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition' => DocumentBaseConfigDefinition::class,
        'Contena\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity' => DocumentBaseConfigEntity::class,
        'Contena\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection' => DocumentBaseConfigSalesChannelCollection::class,
        'Contena\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition' => DocumentBaseConfigSalesChannelDefinition::class,
        'Contena\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity' => DocumentBaseConfigSalesChannelEntity::class,
        'Contena\Core\Checkout\Document\DocumentCollection' => DocumentCollection::class,
        'Contena\Core\Checkout\Document\DocumentDefinition' => DocumentDefinition::class,
        'Contena\Core\Checkout\Document\DocumentEntity' => DocumentEntity::class,
        'Contena\Core\Checkout\Document\Renderer\RenderedDocument' => RenderedDocument::class,
        'Contena\Core\Checkout\Document\SalesChannel\AbstractDocumentRoute' => AbstractDocumentRoute::class,
        'Contena\Core\Checkout\Document\SalesChannel\DocumentRoute' => DocumentRoute::class,
        'Contena\Core\Checkout\Document\Service\ReferenceInvoiceLoader' => ReferenceInvoiceLoader::class,
        'Contena\Core\Framework\Plugin\Util\AssetService' => AssetService::class,
        'Contena\Administration\Controller\NotificationController' => NotificationController::class,
        'Contena\Administration\Notification\NotificationCollection' => NotificationCollection::class,
        'Contena\Administration\Notification\NotificationDefinition' => NotificationDefinition::class,
        'Contena\Administration\Notification\NotificationEntity' => NotificationEntity::class,
        'Contena\Elasticsearch\Product\SearchConfigLoader' => SearchConfigLoader::class,
    ];

    /**
     * @var array<non-empty-string, class-string>
     */
    private static array $packageAliases = [];

    /**
     * Registers aliases contributed by an installed extension from its Composer autoload file.
     *
     * @param array<non-empty-string, class-string> $aliases
     */
    public static function registerAliases(array $aliases): void
    {
        foreach ($aliases as $previousClassName => $currentClassName) {
            $registeredClassName = self::canonicalClassName($previousClassName);
            if ($registeredClassName !== null && $registeredClassName !== $currentClassName) {
                // @phpstan-ignore contena.domainException (Composer bootstrap validates conflicting alias declarations.)
                throw new \LogicException(\sprintf(
                    'Cannot register class alias "%s" to "%s": the alias is already registered for "%s".',
                    $previousClassName,
                    $currentClassName,
                    $registeredClassName
                ));
            }

            if ($registeredClassName === null) {
                self::$packageAliases[$previousClassName] = $currentClassName;
            }

            self::registerAlias($previousClassName, $currentClassName);
        }
    }

    /**
     * @return array<non-empty-string, class-string>
     */
    public static function aliases(): array
    {
        return self::ALIASES + self::$packageAliases;
    }

    private static function canonicalClassName(string $previousClassName): ?string
    {
        foreach (self::aliases() as $registeredPreviousClassName => $registeredClassName) {
            if (strtolower($registeredPreviousClassName) === strtolower($previousClassName)) {
                return $registeredClassName;
            }
        }

        return null;
    }

    /**
     * @param non-empty-string $previousClassName
     * @param class-string $currentClassName
     */
    private static function registerAlias(string $previousClassName, string $currentClassName): void
    {
        if (class_exists($previousClassName, autoload: false)) {
            $registeredClassName = (new \ReflectionClass($previousClassName))->getName();

            if ($registeredClassName !== $currentClassName) {
                // @phpstan-ignore contena.domainException (Composer bootstrap validates conflicting alias declarations.)
                throw new \LogicException(\sprintf('Cannot register class alias "%s" to "%s": the name already refers to "%s".', $previousClassName, $currentClassName, $registeredClassName));
            }

            return;
        }

        if (!class_exists($currentClassName)) {
            // @phpstan-ignore contena.domainException (Composer bootstrap validates contributed aliases.)
            throw new \LogicException(\sprintf('Cannot register class alias "%s" to "%s": the canonical class does not exist.', $previousClassName, $currentClassName));
        }

        class_alias($currentClassName, $previousClassName);
    }

    /**
     * PhpStorm only recognizes explicit class_alias() calls. Keep this method in sync for Core IDE support;
     * ClassAliasRegistry::aliases() remains the authoritative list and this method is never called.
     *
     * @codeCoverageIgnore
     */
    // @phpstan-ignore method.unused (PhpStorm indexes these declarations without calling the method.)
    private static function declareAliasesForIde(): void
    {
        class_alias(DocumentBaseConfigCollection::class, 'Contena\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection');
        class_alias(DocumentBaseConfigDefinition::class, 'Contena\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition');
        class_alias(DocumentBaseConfigEntity::class, 'Contena\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity');
        class_alias(DocumentBaseConfigSalesChannelCollection::class, 'Contena\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelCollection');
        class_alias(DocumentBaseConfigSalesChannelDefinition::class, 'Contena\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition');
        class_alias(DocumentBaseConfigSalesChannelEntity::class, 'Contena\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelEntity');
        class_alias(DocumentCollection::class, 'Contena\Core\Checkout\Document\DocumentCollection');
        class_alias(DocumentDefinition::class, 'Contena\Core\Checkout\Document\DocumentDefinition');
        class_alias(DocumentEntity::class, 'Contena\Core\Checkout\Document\DocumentEntity');
        class_alias(RenderedDocument::class, 'Contena\Core\Checkout\Document\Renderer\RenderedDocument');
        class_alias(AbstractDocumentRoute::class, 'Contena\Core\Checkout\Document\SalesChannel\AbstractDocumentRoute');
        class_alias(DocumentRoute::class, 'Contena\Core\Checkout\Document\SalesChannel\DocumentRoute');
        class_alias(ReferenceInvoiceLoader::class, 'Contena\Core\Checkout\Document\Service\ReferenceInvoiceLoader');
        class_alias(AssetService::class, 'Contena\Core\Framework\Plugin\Util\AssetService');
        class_alias(NotificationController::class, 'Contena\Administration\Controller\NotificationController');
        class_alias(NotificationCollection::class, 'Contena\Administration\Notification\NotificationCollection');
        class_alias(NotificationDefinition::class, 'Contena\Administration\Notification\NotificationDefinition');
        class_alias(NotificationEntity::class, 'Contena\Administration\Notification\NotificationEntity');
        class_alias(SearchConfigLoader::class, 'Contena\Elasticsearch\Product\SearchConfigLoader');
    }
}
