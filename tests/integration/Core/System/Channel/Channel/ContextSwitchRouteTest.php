<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Channel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('channel-api')]
class ContextSwitchRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testInvalidParameters(): void
    {
        $this->getChannelBrowser()->request('PATCH', '/channel-api/context', [
            'countryId' => [Uuid::randomHex(), Uuid::randomHex()],
            'languageId' => [Uuid::randomHex(), Uuid::randomHex()],
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getChannelBrowser()->getResponse()->getStatusCode());

        $errors = $this->decodeResponse();
        static::assertCount(2, $errors['errors']);

        $mapped = [];
        foreach ($errors['errors'] as $error) {
            $mapped[$error['source']['pointer']] = $error['detail'];
        }

        static::assertSame('This value should be of type string.', $mapped['/countryId']);
        static::assertSame('This value should be of type string.', $mapped['/languageId']);
    }

    public function testSwitchToNotExistingLanguage(): void
    {
        $id = Uuid::randomHex();

        $this->getChannelBrowser()->request('PATCH', '/channel-api/context', ['languageId' => $id]);

        $response = $this->getChannelBrowser()->getResponse();
        $content = $this->decodeResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), print_r($content, true));
        static::assertSame(
            \sprintf('The "language" entity with id "%s" does not exist.', $id),
            $content['errors'][0]['detail'] ?? null,
        );
    }

    public function testSwitchToValidLanguage(): void
    {
        $this->getChannelBrowser()->request('PATCH', '/channel-api/context', ['languageId' => Defaults::LANGUAGE_SYSTEM]);

        $response = $this->getChannelBrowser()->getResponse();
        $content = $this->decodeResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($content, true));
    }

    public function testSwitchToNotExistingCountry(): void
    {
        $id = Uuid::randomHex();

        $this->getChannelBrowser()->request('PATCH', '/channel-api/context', ['countryId' => $id]);

        $response = $this->getChannelBrowser()->getResponse();
        $content = $this->decodeResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), print_r($content, true));
        static::assertSame(
            \sprintf('The "country" entity with id "%s" does not exist.', $id),
            $content['errors'][0]['detail'] ?? null,
        );
    }

    public function testSwitchToValidCountry(): void
    {
        $this->getChannelBrowser()->request('PATCH', '/channel-api/context', ['countryId' => $this->getValidCountryId()]);

        $response = $this->getChannelBrowser()->getResponse();
        $content = $this->decodeResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($content, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResponse(): array
    {
        $content = $this->getChannelBrowser()->getResponse()->getContent();
        static::assertIsString($content);

        $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($decoded);

        return $decoded;
    }
}
