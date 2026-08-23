<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Framework\Captcha\BasicCaptcha;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class CaptchaControllerTest extends TestCase
{
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;

    public function testLoadBasicCaptchaContent(): void
    {
        $response = $this->request('GET', 'basic-captcha', []);

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testValidateCaptcha(): void
    {
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
        $response = $this->request(
            'POST',
            'basic-captcha-validate',
            $this->tokenize('frontend.captcha.basic-captcha.validate', $payload),
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );
        static::assertSame(200, $response->getStatusCode());
        static::assertArrayHasKey('session', json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR));

        // BasicCaptcha Invalid
        $this->getSession()->set($formId . 'basic_captcha_session', 'invalid');
        $response = $this->request(
            'POST',
            'basic-captcha-validate',
            $payload,
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
        );
        static::assertSame(200, $response->getStatusCode());
        static::assertArrayHasKey('error', json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)[0]);
    }
}
