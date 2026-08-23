<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\MailTemplate;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class TenantOwnedMailTemplateAggregateTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var array<string, Context>
     */
    private array $contexts;

    private string $mailTemplateTypeId;

    private string $tenantA;

    private string $tenantB;

    protected function setUp(): void
    {
        $this->tenantA = $this->createTenant('Mail template tenant A')->id;
        $this->tenantB = $this->createTenant('Mail template tenant B')->id;
        $this->contexts = [
            'platform' => Context::createDefaultContext(),
            'tenant-a' => Context::createTenantContext($this->tenantA),
            'tenant-b' => Context::createTenantContext($this->tenantB),
            'global' => Context::createGlobalContext(),
        ];

        $typeIds = $this->repository('mail_template_type')
            ->searchIds(new Criteria()->setLimit(1), Context::createDefaultContext())
            ->getIds();
        static::assertNotEmpty($typeIds);
        $this->mailTemplateTypeId = $typeIds[0];
    }

    public function testReadAndWriteMatrix(): void
    {
        $ids = [];
        foreach ($this->contexts as $scope => $context) {
            $ids[$scope] = $this->createAggregate($scope, $context);
        }

        $expectedCounts = [
            'platform' => 2,
            'tenant-a' => 1,
            'tenant-b' => 1,
            'global' => 4,
        ];
        foreach ($this->contexts as $scope => $context) {
            foreach ($this->entityIdMap() as $entityName => $idKey) {
                static::assertCount(
                    $expectedCounts[$scope],
                    $this->repository($entityName)->searchIds(new Criteria(array_column($ids, $idKey)), $context)->getIds(),
                    'Unexpected ' . $entityName . ' rows for ' . $scope,
                );
            }

            $this->assertTranslationCount('mail_template_translation', 'mailTemplateId', array_column($ids, 'template'), $expectedCounts[$scope], $context, $scope);
            $this->assertTranslationCount('mail_header_footer_translation', 'mailHeaderFooterId', array_column($ids, 'headerFooter'), $expectedCounts[$scope], $context, $scope);
        }

        $expectedTenants = [
            'platform' => null,
            'tenant-a' => $this->tenantA,
            'tenant-b' => $this->tenantB,
            'global' => null,
        ];
        foreach ($ids as $scope => $scopeIds) {
            $expectedTenant = $expectedTenants[$scope];
            $this->assertStoredTenant('mail_template', 'id', $scopeIds['template'], $expectedTenant);
            $this->assertStoredTenant('mail_template_translation', 'mail_template_id', $scopeIds['template'], $expectedTenant);
            $this->assertStoredTenant('mail_template_media', 'id', $scopeIds['templateMedia'], $expectedTenant);
            $this->assertStoredTenant('mail_header_footer', 'id', $scopeIds['headerFooter'], $expectedTenant);
            $this->assertStoredTenant('mail_header_footer_translation', 'mail_header_footer_id', $scopeIds['headerFooter'], $expectedTenant);
        }

        $tenantA = $ids['tenant-a'];
        foreach ($this->entityUpdateMap($tenantA, 'tenant-a') as $entityName => $payload) {
            $this->writeUpdate($entityName, $payload, $this->contexts['tenant-a']);
        }

        foreach (['platform', 'tenant-b', 'global'] as $scope) {
            foreach ($this->entityUpdateMap($tenantA, $scope) as $entityName => $payload) {
                $this->assertWriteRejected(
                    fn () => $this->writeUpdate($entityName, $payload, $this->contexts[$scope]),
                    'Expected ' . $entityName . ' write protection for ' . $scope,
                );
            }
        }

        $this->assertWriteRejected(
            fn () => $this->repository('mail_template_media')->create([[
                'id' => Uuid::randomHex(),
                'mailTemplateId' => $ids['tenant-a']['template'],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'mediaId' => $ids['tenant-b']['media'],
                'position' => 10,
            ]], $this->contexts['tenant-a']),
            'Expected a mail template attachment referencing another tenant media row to be rejected',
        );
    }

    /**
     * @return array{media: string, template: string, templateMedia: string, headerFooter: string}
     */
    private function createAggregate(string $scope, Context $context): array
    {
        $mediaId = Uuid::randomHex();
        $templateId = Uuid::randomHex();
        $templateMediaId = Uuid::randomHex();
        $headerFooterId = Uuid::randomHex();

        $this->repository('media')->create([[
            'id' => $mediaId,
            'fileName' => 'mail-template-' . $scope . '.txt',
            'mimeType' => 'text/plain',
        ]], $context);
        $this->repository('mail_template')->create([[
            'id' => $templateId,
            'mailTemplateTypeId' => $this->mailTemplateTypeId,
            'systemDefault' => false,
            'senderName' => 'Tenant matrix sender ' . $scope,
            'subject' => 'Tenant matrix subject ' . $scope,
            'contentHtml' => '<p>Tenant matrix</p>',
            'contentPlain' => 'Tenant matrix',
        ]], $context);
        $this->repository('mail_template_media')->create([[
            'id' => $templateMediaId,
            'mailTemplateId' => $templateId,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'mediaId' => $mediaId,
            'position' => 1,
        ]], $context);
        $this->repository('mail_header_footer')->create([[
            'id' => $headerFooterId,
            'systemDefault' => false,
            'name' => 'Tenant matrix header footer ' . $scope,
            'headerHtml' => '<header>Tenant matrix</header>',
            'headerPlain' => 'Tenant matrix header',
            'footerHtml' => '<footer>Tenant matrix</footer>',
            'footerPlain' => 'Tenant matrix footer',
        ]], $context);

        return [
            'media' => $mediaId,
            'template' => $templateId,
            'templateMedia' => $templateMediaId,
            'headerFooter' => $headerFooterId,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function entityIdMap(): array
    {
        return [
            'mail_template' => 'template',
            'mail_template_media' => 'templateMedia',
            'mail_header_footer' => 'headerFooter',
        ];
    }

    /**
     * @param array{media: string, template: string, templateMedia: string, headerFooter: string} $ids
     *
     * @return array<string, array<string, mixed>>
     */
    private function entityUpdateMap(array $ids, string $value): array
    {
        return [
            'mail_template' => [
                'id' => $ids['template'],
                'mailTemplateTypeId' => $this->mailTemplateTypeId,
                'systemDefault' => true,
            ],
            'mail_template_translation' => [
                'mailTemplateId' => $ids['template'],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'subject' => 'Updated ' . $value,
            ],
            'mail_template_media' => [
                'id' => $ids['templateMedia'],
                'mailTemplateId' => $ids['template'],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'mediaId' => $ids['media'],
                'position' => 2,
            ],
            'mail_header_footer' => ['id' => $ids['headerFooter'], 'systemDefault' => true],
            'mail_header_footer_translation' => [
                'mailHeaderFooterId' => $ids['headerFooter'],
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'name' => 'Updated ' . $value,
            ],
        ];
    }

    /**
     * @param list<string> $parentIds
     */
    private function assertTranslationCount(string $entityName, string $property, array $parentIds, int $expected, Context $context, string $scope): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter($property, $parentIds));

        static::assertCount(
            $expected,
            $this->repository($entityName)->searchIds($criteria, $context)->getIds(),
            'Unexpected ' . $entityName . ' rows for ' . $scope,
        );
    }

    private function assertStoredTenant(string $table, string $idColumn, string $id, ?string $expectedTenantId): void
    {
        $tenantId = static::getContainer()->get(Connection::class)->fetchOne(
            \sprintf('SELECT LOWER(HEX(`tenant_id`)) FROM `%s` WHERE `%s` = :id', $table, $idColumn),
            ['id' => Uuid::fromHexToBytes($id)],
        );

        static::assertSame($expectedTenantId, $tenantId === false ? null : $tenantId);
    }

    private function assertWriteRejected(\Closure $write, string $message): void
    {
        try {
            $write();
            static::fail($message);
        } catch (WriteException) {
        }
    }

    /**
     * Mapping entities use upsert for existing rows in the current DAL write intent model.
     *
     * @param array<string, mixed> $payload
     */
    private function writeUpdate(string $entityName, array $payload, Context $context): void
    {
        if ($entityName === 'mail_template_media') {
            $this->repository($entityName)->upsert([$payload], $context);

            return;
        }

        $this->repository($entityName)->update([$payload], $context);
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $entityName): EntityRepository
    {
        $repository = static::getContainer()->get($entityName . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}
