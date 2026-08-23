<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Captcha;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Framework\Captcha\BasicCaptcha;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class CaptchaRouteListenerTest extends TestCase
{
    use FrontendControllerTestBehaviour;
    use IntegrationTestBehaviour;

    public function testResponseWhenCaptchaValidationFails(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            BasicCaptcha::CAPTCHA_NAME => [
                'name' => BasicCaptcha::CAPTCHA_NAME,
                'isActive' => true,
            ],
        ]);

        $data = [
            'contena_basic_captcha_confirm' => 'kyln',
        ];

        $response = $this->request(
            'POST',
            'account/register',
            $this->tokenize('frontend.account.register.save', $data)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent() ?: '');
    }

    public function testCaptchaFailureRespectsErrorRoute(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.basicInformation.activeCaptchasV2', [
            BasicCaptcha::CAPTCHA_NAME => [
                'name' => BasicCaptcha::CAPTCHA_NAME,
                'isActive' => true,
            ],
        ]);

        $data = [
            'contena_basic_captcha_confirm' => 'invalid',
            'errorRoute' => 'frontend.account.register.page',
        ];

        $response = $this->request(
            'POST',
            'account/register',
            $this->tokenize('frontend.account.register.save', $data)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent() ?: '');

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('window.activeRoute = \'frontend.account.register.page\'', $content);
    }
}
