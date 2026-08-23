<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Captcha\AbstractCaptcha;
use Contena\Frontend\Framework\Captcha\BasicCaptcha;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Pagelet\Captcha\AbstractBasicCaptchaPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class CaptchaController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractBasicCaptchaPageletLoader $basicCaptchaPageletLoader,
        private readonly AbstractCaptcha $basicCaptcha,
    ) {
    }

    #[Route(path: '/basic-captcha', name: 'frontend.captcha.basic-captcha.load', defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function loadBasicCaptcha(Request $request, ChannelContext $context): Response
    {
        $formId = $request->query->get('formId');
        $page = $this->basicCaptchaPageletLoader->load($request, $context);
        $request->getSession()->set($formId . BasicCaptcha::BASIC_CAPTCHA_SESSION, $page->getCaptcha()->getCode());

        return $this->renderFrontend('@Frontend/frontend/component/captcha/basicCaptchaImage.html.twig', [
            'page' => $page,
            'formId' => $formId,
        ]);
    }

    #[Route(path: '/basic-captcha-validate', name: 'frontend.captcha.basic-captcha.validate', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        $response = [];
        $formId = RequestParamHelper::get($request, 'formId');
        if (!$formId) {
            throw RoutingException::missingRequestParameter('formId');
        }

        if ($this->basicCaptcha->isValid($request, [])) {
            $fakeSession = RequestParamHelper::get($request, BasicCaptcha::CAPTCHA_REQUEST_PARAMETER);
            $request->getSession()->set($formId . BasicCaptcha::BASIC_CAPTCHA_SESSION, $fakeSession);

            return new JsonResponse(['session' => $fakeSession]);
        }

        $response[] = [
            'type' => 'danger',
            'error' => 'invalid_captcha',
        ];

        return new JsonResponse($response);
    }
}
