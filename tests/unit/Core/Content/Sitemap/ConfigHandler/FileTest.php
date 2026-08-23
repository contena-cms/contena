<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Sitemap\ConfigHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Sitemap\ConfigHandler\File;
use Contena\Core\Content\Sitemap\Service\ConfigHandler;

/**
 * @internal
 */
#[CoversClass(File::class)]
class FileTest extends TestCase
{
    public function testAddLastModDate(): void
    {
        $fileConfigHandler = new File([
            ConfigHandler::EXCLUDED_URLS_KEY => [],
            ConfigHandler::CUSTOM_URLS_KEY => [
                [
                    'url' => 'foo',
                    'changeFreq' => 'weekly',
                    'priority' => 0.5,
                    'channelId' => 2,
                    'lastMod' => '2019-09-27 10:00:00',
                ],
            ],
        ]);

        $customUrl = $fileConfigHandler->getSitemapConfig()[ConfigHandler::CUSTOM_URLS_KEY][0];

        static::assertInstanceOf(\DateTimeInterface::class, $customUrl['lastMod']);
    }
}
