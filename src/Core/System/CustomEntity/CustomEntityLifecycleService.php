<?php
declare(strict_types=1);

namespace Contena\Core\System\CustomEntity;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Contena\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Contena\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchema;
use Contena\Core\System\CustomEntity\Xml\Config\CustomEntityEnrichmentService;
use Contena\Core\System\CustomEntity\Xml\CustomEntityXmlSchema;
use Contena\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Contena\Core\System\CustomEntity\Xml\Field\AssociationField;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
class CustomEntityLifecycleService
{
    /**
     * @param EntityRepository<CustomEntityCollection> $customEntityRepository
     */
    public function __construct(
        private readonly CustomEntityPersister $customEntityPersister,
        private readonly CustomEntitySchemaUpdater $customEntitySchemaUpdater,
        private readonly CustomEntityEnrichmentService $customEntityEnrichmentService,
        private readonly CustomEntityXmlSchemaValidator $customEntityXmlSchemaValidator,
        private readonly SourceResolver $sourceResolver,
        private readonly Connection $connection,
        private readonly EntityRepository $customEntityRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function updateApp(AppEntity $app): ?CustomEntityXmlSchema
    {
        $fs = $this->sourceResolver->filesystemForApp($app);

        if (!$fs->has('Resources')) {
            return null;
        }

        return $this->update(
            $fs->path('Resources'),
            AppEntity::class,
            $app->getId()
        );
    }

    public function allowsDisabling(AppEntity $app): bool
    {
        $entities = $this->connection->fetchFirstColumn(
            'SELECT fields FROM custom_entity WHERE app_id = :id',
            ['id' => Uuid::fromHexToBytes($app->getId())]
        );

        foreach ($entities as $fields) {
            $fields = json_decode((string) $fields, true, 512, \JSON_THROW_ON_ERROR);

            foreach ($fields as $field) {
                $restricted = $field['onDelete'] ?? null;

                if ($restricted === AssociationField::RESTRICT) {
                    return false;
                }
            }
        }

        return true;
    }

    public function canRemoveAppData(AppEntity $app): bool
    {
        $entities = $this->connection->fetchAllKeyValue(
            'SELECT name, fields FROM custom_entity WHERE app_id = :id',
            ['id' => Uuid::fromHexToBytes($app->getId())]
        );

        foreach ($entities as $table => $fields) {
            $fields = json_decode((string) $fields, true, 512, \JSON_THROW_ON_ERROR);

            foreach ($fields as $field) {
                $restricted = $field['onDelete'] ?? null;

                if ($restricted !== AssociationField::RESTRICT) {
                    continue;
                }

                if ($this->tableHasRows((string) $table)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function removeApp(AppEntity $app, Context $context, bool $keepUserData): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $app->getId()));

        $customEntities = $this->customEntityRepository->search($criteria, $context)->getEntities();
        if ($customEntities->count() === 0) {
            return;
        }

        if ($keepUserData) {
            $deletedAt = $this->clock->now();

            $this->customEntityRepository->update(array_values(array_map(
                static fn (CustomEntityEntity $customEntity): array => [
                    'id' => $customEntity->getId(),
                    'appId' => null,
                    'deletedAt' => $deletedAt,
                ],
                $customEntities->getElements()
            )), $context);

            return;
        }

        $this->customEntityRepository->delete(array_values(array_map(
            static fn (string $id): array => ['id' => $id],
            $customEntities->getIds()
        )), $context);
        $this->customEntitySchemaUpdater->update();
    }

    private function tableHasRows(string $table): bool
    {
        return (int) $this->connection->fetchOne(\sprintf('SELECT COUNT(*) FROM %s', $this->connection->quoteSingleIdentifier($table))) > 0;
    }

    private function update(string $pathToCustomEntityFile, string $extensionEntityType, string $extensionId): ?CustomEntityXmlSchema
    {
        $customEntityXmlSchema = $this->getXmlSchema($pathToCustomEntityFile);
        if ($customEntityXmlSchema === null) {
            return null;
        }

        $customEntityXmlSchema = $this->customEntityEnrichmentService->enrich(
            $customEntityXmlSchema,
            $this->getAdminUiXmlSchema($pathToCustomEntityFile),
        );

        $this->customEntityPersister->update($customEntityXmlSchema->toStorage(), $extensionEntityType, $extensionId);
        $this->customEntitySchemaUpdater->update();

        return $customEntityXmlSchema;
    }

    private function getXmlSchema(string $pathToCustomEntityFile): ?CustomEntityXmlSchema
    {
        $filePath = Path::join($pathToCustomEntityFile, CustomEntityXmlSchema::FILENAME);
        if (!\is_file($filePath)) {
            return null;
        }

        $customEntityXmlSchema = CustomEntityXmlSchema::createFromXmlFile($filePath);
        $this->customEntityXmlSchemaValidator->validate($customEntityXmlSchema);

        return $customEntityXmlSchema;
    }

    private function getAdminUiXmlSchema(string $pathToCustomEntityFile): ?AdminUiXmlSchema
    {
        $configPath = Path::join($pathToCustomEntityFile, 'config', AdminUiXmlSchema::FILENAME);

        if (!\is_file($configPath)) {
            return null;
        }

        return AdminUiXmlSchema::createFromXmlFile($configPath);
    }
}
