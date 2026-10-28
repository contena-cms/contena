<?php declare(strict_types=1);

namespace Contena\Storefront\Controller;

use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Framework\Captcha\AbstractCaptcha;
use Contena\Storefront\Framework\Captcha\BasicCaptcha;
use Contena\Storefront\Framework\Routing\StorefrontRouteScope;
use Contena\Storefront\Pagelet\Captcha\AbstractBasicCaptchaPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class CaptchaController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractBasicCaptchaPageletLoader $basicCaptchaPageletLoader,
        private readonly AbstractCaptcha $basicCaptcha
    ) {
    }

    #[Route(path: '/basic-captcha', name: 'frontend.captcha.basic-captcha.load', defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function loadBasicCaptcha(Request $request, SalesChannelContext $context): Response
    {
        $formId = $request->query->get('formId');
        $page = $this->basicCaptchaPageletLoader->load($request, $context);
        $request->getSession()->set($formId . BasicCaptcha::BASIC_CAPTCHA_SESSION, $page->getCaptcha()->getCode());

        return $this->renderStorefront('@Storefront/storefront/component/captcha/basicCaptchaImage.html.twig', [
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

        if ($this->basicCaptcha->validate($request, [])->count() === 0) {
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
