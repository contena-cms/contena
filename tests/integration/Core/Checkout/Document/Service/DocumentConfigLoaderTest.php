<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Document\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Document\DocumentConfigurationFactory;
use Contena\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Contena\Core\Checkout\Document\Service\DocumentConfigLoader;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;
use Contena\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
class DocumentConfigLoaderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use DocumentTrait;

    private ChannelContext $channelContext;

    private Context $context;

    private DocumentConfigLoader $documentConfigLoader;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $userId = $this->createUser();

        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $userId,
            ]
        );

        $this->documentConfigLoader = static::getContainer()->get(DocumentConfigLoader::class);

        $this->connection = static::getContainer()->get(Connection::class);
    }

    protected function tearDown(): void
    {
        $this->documentConfigLoader->reset();
    }

    public function testLoadGlobalConfig(): void
    {
        $this->addLogoToDocument('invoice');

        $base = $this->getBaseConfig('invoice');
        $globalConfig = $base === null ? [] : $base->getConfig();
        $globalConfig['companyName'] = 'Test corp.';
        $globalConfig['displayCompanyAddress'] = true;
        $this->upsertBaseConfig($globalConfig, 'invoice');

        $channelId = $this->channelContext->getChannelId();
        $config = $this->documentConfigLoader->load('invoice', $channelId, $this->context);

        $config = $config->jsonSerialize();

        static::assertSame('Test corp.', $config['companyName']);
        static::assertTrue($config['displayCompanyAddress']);
        static::assertNotNull($config['logo']);
        static::assertInstanceOf(MediaEntity::class, $config['logo']);
    }

    public function testLoadChannelConfig(): void
    {
        $base = $this->getBaseConfig('invoice');

        $globalConfig = DocumentConfigurationFactory::createConfiguration([
            'companyName' => 'Test corp.',
            'displayCompanyAddress' => true,
        ], $base);

        $this->upsertBaseConfig($globalConfig->jsonSerialize(), InvoiceRenderer::TYPE);

        $channelConfig = DocumentConfigurationFactory::mergeConfiguration($globalConfig, [
            'companyName' => 'Custom corp.',
            'displayCompanyAddress' => false,
            'pageSize' => 'a5',
        ]);

        $channelId = $this->channelContext->getChannelId();
        $this->upsertBaseConfig($channelConfig->jsonSerialize(), InvoiceRenderer::TYPE, $channelId);

        $config = $this->documentConfigLoader->load(InvoiceRenderer::TYPE, $channelId, $this->context);

        $config = $config->jsonSerialize();

        static::assertSame('Custom corp.', $config['companyName']);
        static::assertFalse($config['displayCompanyAddress']);
        static::assertSame('a5', $config['pageSize']);
    }

    private function addLogoToDocument(string $configName): void
    {
        $qb = $this->connection->createQueryBuilder();

        $mediaId = $qb->select('media.id AS ID')
            ->from('media')
            ->fetchOne();

        $qb->update('document_base_config')
            ->set('document_base_config.logo_id', ':id')
            ->where('document_base_config.name = :name')
            ->setParameter('id', $mediaId)
            ->setParameter('name', $configName)
            ->executeStatement();
    }
}
