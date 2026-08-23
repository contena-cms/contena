<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme\Mail;

use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\Mail\MailThemeIdLoader;

/**
 * @internal
 */
class MailThemeIdLoaderTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testLoadsThemeIdForChannel(): void
    {
        $themeId = $this->getThemeId();

        $channel = $this->createChannelWithUniqueDomain([
            'themes' => [
                [
                    'id' => $themeId,
                ],
            ],
        ]);

        static::assertSame($themeId, static::getContainer()->get(MailThemeIdLoader::class)->load($channel['id']));
    }

    public function testReturnsNullWhenChannelHasNoTheme(): void
    {
        $channel = $this->createChannelWithUniqueDomain();

        static::assertNull(static::getContainer()->get(MailThemeIdLoader::class)->load($channel['id']));
    }

    /**
     * @param array<string, mixed> $channelOverride
     *
     * @return array<string, mixed>
     */
    private function createChannelWithUniqueDomain(array $channelOverride = []): array
    {
        return $this->createChannel(array_replace_recursive([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost/' . Uuid::randomHex(),
                ],
            ],
        ], $channelOverride));
    }

    private function getThemeId(): string
    {
        $id = static::getContainer()->get('theme.repository')->searchIds(new Criteria(), Context::createDefaultContext())->firstId();

        static::assertIsString($id);

        return $id;
    }
}
