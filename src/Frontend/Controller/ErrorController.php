<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRoute;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Framework\Twig\ErrorTemplateResolver;
use Contena\Frontend\Page\Navigation\Error\ErrorPageLoaderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data
 */
class ErrorController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ErrorTemplateResolver $errorTemplateResolver,
        private readonly SystemConfigService $systemConfigService,
        private readonly ErrorPageLoaderInterface $errorPageLoader,
        private readonly AbstractContentRoute $contentRoute,
    ) {
    }

    public function error(\Throwable $exception, Request $request, ChannelContext $context): Response
    {
        /** @phpstan-ignore contena.unsafeRequestHasSession (using $skipIfUninitialized = false as session will be started intentionally later; this can take the PHP session lock and is limited to frontend error rendering reading flash messages.) */
        $session = $request->hasSession() ? $request->getSession() : null;

        try {
            $is404StatusCode = $exception instanceof HttpException
                && $exception->getStatusCode() === Response::HTTP_NOT_FOUND;

            if (!$is404StatusCode && $session instanceof FlashBagAwareSessionInterface && !$session->getFlashBag()->has('danger')) {
                $session->getFlashBag()->add('danger', $this->trans('error.message-default'));
            }

            $request->attributes->set('navigationId', $context->getChannel()->getNavigationCategoryId());

            $channelId = $context->getChannelId();
            $errorLandingPageId = $this->systemConfigService->getString('core.basicInformation.http404Page', $channelId);
            if ($errorLandingPageId !== '' && $is404StatusCode) {
                $errorPage = $this->errorPageLoader->load($errorLandingPageId, $request, $context);
                $contentResponse = $this->contentRoute->load('/landing-page/' . $errorLandingPageId, $request, $context);
                \assert($contentResponse instanceof ContentRouteResponse);

                $response = $this->renderFrontend(
                    '@Frontend/frontend/page/landing-page/index.html.twig',
                    [
                        'page' => $errorPage,
                        'contentPage' => $contentResponse->getContentPage(),
                        'isNewContentStructure' => true,
                    ]
                );
            } else {
                $errorTemplate = $this->errorTemplateResolver->resolve($exception, $request);
                $response = $this->renderFrontend($errorTemplate->getTemplateName(), ['page' => $errorTemplate]);
            }

            if ($exception instanceof HttpException) {
                $response->setStatusCode($exception->getStatusCode());
            }
        } catch (\Exception $followingException) {
            $response = $this->renderFrontend(
                '@Frontend/frontend/page/error/index.html.twig',
                ['exception' => $exception, 'followingException' => $followingException]
            );
            $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->clear();
        }

        return $response;
    }

    public function onCaptchaFailure(ConstraintViolationList $violations, Request $request): Response
    {
        $formViolations = new ConstraintViolationException($violations, []);
        if (!$request->isXmlHttpRequest()) {
            $errorRoute = (string) $request->request->get('errorRoute');
            $route = $errorRoute !== '' ? $errorRoute : (($fallback = $request->attributes->getString('_route')) !== '' ? $fallback : 'frontend.home.page');

            return $this->forwardToRoute($route, ['formViolations' => $formViolations]);
        }

        return new JsonResponse([[
            'type' => 'danger',
            'error' => 'invalid_captcha',
            'alert' => $this->renderView('@Frontend/frontend/utilities/alert.html.twig', [
                'type' => 'danger',
                'list' => [$this->trans('error.' . $formViolations->getViolations()->get(0)->getCode())],
            ]),
        ]]);
    }
}
