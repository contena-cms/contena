<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Framework\Captcha\GoogleReCaptchaV2;
use Contena\Frontend\Framework\Captcha\GoogleReCaptchaV3;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class CookieControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private TestBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = KernelLifecycleManager::createBrowser($this->getKernel());
    }

    public function testCookieRequiredGroupIncludeGoogleReCaptchaWhenActive(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            GoogleReCaptchaV2::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV2::CAPTCHA_NAME,
                'isActive' => false,
                'config' => [
                    'siteKey' => 'siteKey',
                    'secretKey' => 'secretKey',
                    'invisible' => false,
                ],
            ],
            GoogleReCaptchaV3::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV3::CAPTCHA_NAME,
                'isActive' => false,
                'config' => [
                    'siteKey' => 'siteKey',
                    'secretKey' => 'secretKey',
                    'invisible' => false,
                ],
            ],
        ]);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_technically-required"]'));
        static::assertCount(0, $crawler->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            GoogleReCaptchaV2::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV2::CAPTCHA_NAME,
                'isActive' => true,
                'config' => [
                    'siteKey' => 'siteKey',
                    'secretKey' => 'secretKey',
                    'invisible' => false,
                ],
            ],
        ]);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_technically-required"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));

        $systemConfig->set('core.basicInformation.activeCaptchasV3', [
            GoogleReCaptchaV3::CAPTCHA_NAME => [
                'name' => GoogleReCaptchaV3::CAPTCHA_NAME,
                'isActive' => true,
                'config' => [
                    'siteKey' => 'siteKey',
                    'secretKey' => 'secretKey',
                    'invisible' => false,
                ],
            ],
        ]);

        $crawler = $this->browser->request('GET', $_SERVER['APP_URL'] . '/cookie/offcanvas');

        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());

        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie_technically-required"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="cookie__GRECAPTCHA"]'));
    }

    public function testLogConsentPersistsDecisionAndConfigSnapshot(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $payload = (string) json_encode([
            'consentAction' => 'accept_all',
            'acceptedGroups' => ['cookie.groupRequired', 'cookie.groupStatistical'],
        ]);

        $this->browser->request('POST', $_SERVER['APP_URL'] . '/cookie/consent-log', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->browser->getResponse()->getStatusCode());

        $logs = $connection->fetchAllAssociative('SELECT * FROM `cookie_consent_log`');
        static::assertCount(1, $logs);
        static::assertSame('accept_all', $logs[0]['consent_action']);
        static::assertSame(['cookie.groupRequired', 'cookie.groupStatistical'], json_decode((string) $logs[0]['accepted_groups'], true));

        // The banner snapshot was stored under the same hash as the log entry
        $configVersions = $connection->fetchAllAssociative('SELECT * FROM `cookie_consent_config_version`');
        static::assertCount(1, $configVersions);
        static::assertSame($logs[0]['config_hash'], $configVersions[0]['config_hash']);
        static::assertJson((string) $configVersions[0]['cookie_groups']);

        // A second consent adds a log entry but no duplicate snapshot
        $this->browser->request('POST', $_SERVER['APP_URL'] . '/cookie/consent-log', [], [], ['CONTENT_TYPE' => 'application/json'], $payload);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->browser->getResponse()->getStatusCode());

        static::assertCount(2, $connection->fetchAllAssociative('SELECT * FROM `cookie_consent_log`'));
        static::assertCount(1, $connection->fetchAllAssociative('SELECT * FROM `cookie_consent_config_version`'));
    }

    public function testLogConsentRejectsInvalidPayload(): void
    {
        $this->browser->request('POST', $_SERVER['APP_URL'] . '/cookie/consent-log', [], [], ['CONTENT_TYPE' => 'application/json'], '{"consentAction": "invalid"}');

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
    }
}
