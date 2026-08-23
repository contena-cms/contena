<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerSearchIdsTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testSearchIdsOnManyToMany(): void
    {
        $configurationId = Uuid::randomHex();
        $thumbnailSizeA = Uuid::randomHex();
        $thumbnailSizeB = Uuid::randomHex();

        static::getContainer()->get('media_folder_configuration.repository')->create([
            [
                'id' => $configurationId,
                'mediaThumbnailSizes' => [
                    ['id' => $thumbnailSizeA, 'width' => 731, 'height' => 731],
                    ['id' => $thumbnailSizeB, 'width' => 733, 'height' => 733],
                ],
            ],
        ], Context::createDefaultContext());

        $path = '/api/search-ids/media-folder-configuration-media-thumbnail-size';
        $this->getBrowser()->jsonRequest('POST', $path, [
            'filter' => [
                [
                    'type' => 'equalsAny',
                    'field' => 'mediaFolderConfigurationId',
                    'value' => $configurationId,
                ],
            ],
        ]);
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));

        static::assertIsArray($responseData);
        static::assertArrayHasKey('total', $responseData);
        static::assertSame(2, $responseData['total']);
        static::assertArrayHasKey('data', $responseData);

        $thumbnailSizeAFound = 0;
        $thumbnailSizeBFound = 0;

        foreach ($responseData['data'] as $datum) {
            static::assertArrayHasKey('mediaFolderConfigurationId', $datum);
            static::assertArrayHasKey('mediaThumbnailSizeId', $datum);
            static::assertSame($datum['mediaFolderConfigurationId'], $configurationId);

            if ($thumbnailSizeA === $datum['mediaThumbnailSizeId']) {
                ++$thumbnailSizeAFound;
            }

            if ($thumbnailSizeB === $datum['mediaThumbnailSizeId']) {
                ++$thumbnailSizeBFound;
            }
        }

        static::assertSame(1, $thumbnailSizeAFound);
        static::assertSame(1, $thumbnailSizeBFound);
    }
}
