<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Framework\Captcha\BasicCaptcha;

/**
 * @internal
 */
class CaptchaControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;
    use FrontendControllerTestBehaviour;

    public function testLoadBasicCaptchaContent(): void
    {
        $browser = $this->createCustomChannelBrowser();

        $browser->request('GET', '/basic-captcha');

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testValidateCaptcha(): void
    {
        $browser = $this->createCustomChannelBrowser();
        $browser->setServerParameter('HTTP_X-Requested-With', 'XMLHttpRequest');

        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            BasicCaptcha::CAPTCHA_NAME => [
                'name' => BasicCaptcha::CAPTCHA_NAME,
                'isActive' => true,
            ],
        ]);

        $formId = 'Kyln-test';
        $basicCaptchaSession = 'kylnsession';
        $this->getSession()->set($formId . 'basic_captcha_session', 'kylnsession');

        $payload = [
            'formId' => $formId,
            'contena_basic_captcha_confirm' => $basicCaptchaSession,
        ];

        // Basic Captcha Valid
        $browser->request('POST', '/basic-captcha-validate', $this->tokenize('frontend.captcha.basic-captcha.validate', $payload));

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());
        static::assertArrayHasKey('session', json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));

        // BasicCaptcha Invalid
        $this->getSession()->set($formId . 'basic_captcha_session', 'invalid');
        $browser->request('POST', '/basic-captcha-validate', $payload);

        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode());
        static::assertArrayHasKey('error', json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)[0]);
    }
}
