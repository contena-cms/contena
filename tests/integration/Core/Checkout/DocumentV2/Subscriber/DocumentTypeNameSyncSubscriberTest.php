<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\DocumentV2\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Contena\Core\Checkout\DocumentV2\Aggregate\DocumentBaseConfigChannel\DocumentBaseConfigChannelEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class DocumentTypeNameSyncSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testTypeNameIsWrittenFromDocumentTypeIdOnBaseConfigWrite(): void
    {
        $context = Context::createDefaultContext();
        $documentTypeId = $this->getDocumentTypeId('invoice', $context);

        $id = Uuid::randomHex();
        static::getContainer()->get('document_base_config.repository')->create([[
            'id' => $id,
            'name' => 'test config',
            'documentTypeId' => $documentTypeId,
            'global' => false,
        ]], $context);

        $config = static::getContainer()->get('document_base_config.repository')
            ->search(new Criteria([$id]), $context)->getEntities()->first();

        static::assertInstanceOf(DocumentBaseConfigEntity::class, $config);
        static::assertSame('invoice', $config->getTypeName());
    }

    public function testTypeNameIsWrittenForChannelConfigWrite(): void
    {
        $context = Context::createDefaultContext();
        $documentTypeId = $this->getDocumentTypeId('invoice', $context);

        $channelConfigId = Uuid::randomHex();
        static::getContainer()->get('document_base_config.repository')->create([[
            'id' => Uuid::randomHex(),
            'name' => 'test config',
            'documentTypeId' => $documentTypeId,
            'global' => false,
            'channels' => [[
                'id' => $channelConfigId,
                'channelId' => TestDefaults::CHANNEL,
                'documentTypeId' => $documentTypeId,
            ]],
        ]], $context);

        $channelConfig = static::getContainer()->get('document_base_config_channel.repository')
            ->search(new Criteria([$channelConfigId]), $context)->getEntities()->first();

        static::assertInstanceOf(DocumentBaseConfigChannelEntity::class, $channelConfig);
        static::assertSame('invoice', $channelConfig->getTypeName());
    }

    private function getDocumentTypeId(string $technicalName, Context $context): string
    {
        $id = static::getContainer()->get('document_type.repository')
            ->searchIds((new Criteria())->addFilter(new EqualsFilter('technicalName', $technicalName)), $context)
            ->firstId();

        static::assertIsString($id);

        return $id;
    }
}
