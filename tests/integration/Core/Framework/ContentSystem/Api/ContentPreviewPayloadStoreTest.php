<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\ContentSystem\Api;

use Contena\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class ContentPreviewPayloadStoreTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('the container-wired validator enforces the constraints the DTO declares')]
    public function testLoadRejectsAConstraintViolationThroughTheContainerValidator(): void
    {
        $token = Uuid::randomHex();
        $key = 'content-system.preview.' . $token;
        $cache = static::getContainer()->get('cache.system');

        $item = $cache->getItem($key);
        $item->set([
            'layout' => [['id' => Uuid::randomHex(), 'component' => 'Ct:Content:Text']],
            'entityType' => '',
            'entityId' => Uuid::randomHex(),
            'channelId' => Uuid::randomHex(),
            'languageId' => null,
            'domainId' => null,
            'memberId' => null,
            'queryParameters' => [],
        ]);
        $cache->save($item);

        try {
            $this->expectExceptionObject(ContentSystemException::previewPayloadInvalid(
                'entityType',
                'accepted by the constraints ContentPreviewRequest declares',
                'This value should not be blank.',
            ));

            static::getContainer()->get(ContentPreviewPayloadStore::class)->load($token);
        } finally {
            $cache->deleteItem($key);
        }
    }
}
