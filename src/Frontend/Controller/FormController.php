<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Content\ContactForm\Channel\AbstractContactFormRoute;
use Contena\Core\Content\Newsletter\Channel\AbstractNewsletterSubscribeRoute;
use Contena\Core\Content\Newsletter\Channel\AbstractNewsletterUnsubscribeRoute;
use Contena\Core\Content\RevocationRequest\Channel\AbstractRevocationRequestRoute;
use Contena\Core\Framework\Adapter\Translation\ConstraintViolationTranslator;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class FormController extends FrontendController
{
    final public const SUBSCRIBE = 'subscribe';
    /**
     * @deprecated tag:v6.8.0 - Will be removed with the next major, as it is unused
     */
    final public const UNSUBSCRIBE = 'unsubscribe';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContactFormRoute $contactFormRoute,
        private readonly AbstractNewsletterSubscribeRoute $subscribeRoute,
        private readonly AbstractNewsletterUnsubscribeRoute $unsubscribeRoute,
        private readonly AbstractRevocationRequestRoute $abstractRevocationRequestRoute,
        private readonly ConstraintViolationTranslator $constraintViolationTranslator,
    ) {
    }

    #[Route(
        path: '/form/contact',
        name: 'frontend.form.contact.send',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_CAPTCHA => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function sendContactForm(RequestDataBag $data, ChannelContext $context): JsonResponse
    {
        $response = [];

        try {
            $message = $this->contactFormRoute
                ->load($data->toRequestDataBag(), $context)
                ->getResult()
                ->getIndividualSuccessMessage();

            if (!$message) {
                $message = $this->trans('contact.success');
            }
            $response[] = [
                'type' => 'success',
                'alert' => $message,
            ];
        } catch (ConstraintViolationException $formViolations) {
            $violations = [];
            foreach ($formViolations->getViolations() as $violation) {
                $violations[] = $this->constraintViolationTranslator->translate($violation);
            }
            $response[] = [
                'type' => 'danger',
                'alert' => $this->renderView('@Frontend/frontend/utilities/alert.html.twig', [
                    'type' => 'danger',
                    'list' => $violations,
                ]),
            ];
        } catch (RateLimitExceededException $exception) {
            $response[] = [
                'type' => 'info',
                'alert' => $this->renderView('@Frontend/frontend/utilities/alert.html.twig', [
                    'type' => 'info',
                    'content' => $this->trans('error.rateLimitExceeded', ['%seconds%' => $exception->getWaitTime()]),
                ]),
            ];
        }

        return new JsonResponse($response);
    }

    #[Route(
        path: '/form/newsletter',
        name: 'frontend.form.newsletter.register.handle',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_CAPTCHA => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function handleNewsletter(Request $request, RequestDataBag $data, ChannelContext $context): JsonResponse
    {
        $subscribe = $data->get('option') === self::SUBSCRIBE;

        if ($subscribe) {
            $response = $this->handleSubscribe($request, $data, $context);
        } else {
            $response = $this->handleUnsubscribe($data, $context);
        }

        return new JsonResponse($response);
    }

    #[Route(
        path: '/form/revocation/request',
        name: 'frontend.form.revocation.request',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_CAPTCHA => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function sendRevocationRequest(RequestDataBag $data, ChannelContext $context): JsonResponse
    {
        $response = [];

        try {
            $message = $this->abstractRevocationRequestRoute->request($data, $context)
                ->getIndividualSuccessMessage();

            if ($message === '') {
                $message = $this->trans('revocationRequest.success');
            }

            $response[] = [
                'type' => 'success',
                'alert' => $message,
            ];
        } catch (ConstraintViolationException $formViolations) {
            $violations = [];
            foreach ($formViolations->getViolations() as $violation) {
                $violations[] = $this->constraintViolationTranslator->translate($violation);
            }
            $response[] = [
                'type' => 'danger',
                'alert' => $this->renderView('@Frontend/frontend/utilities/alert.html.twig', [
                    'type' => 'danger',
                    'list' => $violations,
                ]),
            ];
        } catch (RateLimitExceededException $exception) {
            $response[] = [
                'type' => 'info',
                'alert' => $this->renderView('@Frontend/frontend/utilities/alert.html.twig', [
                    'type' => 'info',
                    'content' => $this->trans('error.rateLimitExceeded', ['%seconds%' => $exception->getWaitTime()]),
                ]),
            ];
        }

        return new JsonResponse($response);
    }

    /**
     * @return list<array<string|int, mixed>>
     */
    private function handleSubscribe(Request $request, RequestDataBag $data, ChannelContext $context): array
    {
        $response = [];

        try {
            $data->set('frontendUrl', $request->attributes->get(RequestTransformer::FRONTEND_URL));

            $this->subscribeRoute->subscribeWithResponse($data, $context, false);

            $response[] = [
                'type' => 'success',
                'alert' => $this->trans('newsletter.subscriptionPersistedSuccess'),
            ];
            $response[] = [
                'type' => 'info',
                'alert' => $this->renderView('@Frontend/frontend/utilities/alert.html.twig', [
                    'type' => 'info',
                    'list' => [$this->trans('newsletter.subscriptionPersistedInfo')],
                ]),
            ];
        } catch (ConstraintViolationException $exception) {
            $errors = [];
            foreach ($exception->getViolations() as $violation) {
                $errors[] = $this->constraintViolationTranslator->translate($violation);
            }
            $response[] = [
                'type' => 'danger',
                'alert' => $this->renderView('@Frontend/frontend/utilities/alert.html.twig', [
                    'type' => 'danger',
                    'list' => $errors,
                ]),
            ];
        } catch (RateLimitExceededException $exception) {
            $response[] = [
                'type' => 'info',
                'alert' => $this->renderView('@Frontend/frontend/utilities/alert.html.twig', [
                    'type' => 'info',
                    'content' => $this->trans('error.rateLimitExceeded', ['%seconds%' => $exception->getWaitTime()]),
                ]),
            ];
        } catch (\Exception) {
            $response[] = [
                'type' => 'danger',
                'alert' => $this->renderView('@Frontend/frontend/utilities/alert.html.twig', [
                    'type' => 'danger',
                    'list' => [$this->trans('error.message-default')],
                ]),
            ];
        }

        return $response;
    }

    /**
     * @return list<array<string|int, mixed>>
     */
    private function handleUnsubscribe(RequestDataBag $data, ChannelContext $context): array
    {
        $response = [];

        try {
            $this->unsubscribeRoute->unsubscribeWithResponse($data, $context);
            $response[] = [
                'type' => 'success',
                'alert' => $this->trans('newsletter.subscriptionRevokeSuccess'),
            ];
        } catch (ConstraintViolationException $exception) {
            $errors = [];
            foreach ($exception->getViolations() as $violation) {
                $errors[] = $this->constraintViolationTranslator->translate($violation);
            }
            $response[] = [
                'type' => 'danger',
                'alert' => $this->renderView('@Frontend/frontend/utilities/alert.html.twig', [
                    'type' => 'danger',
                    'list' => $errors,
                ]),
            ];
        } catch (RateLimitExceededException $exception) {
            $response[] = [
                'type' => 'info',
                'alert' => $this->renderView('@Frontend/frontend/utilities/alert.html.twig', [
                    'type' => 'info',
                    'content' => $this->trans('error.rateLimitExceeded', ['%seconds%' => $exception->getWaitTime()]),
                ]),
            ];
        } catch (\Exception) {
        }

        return $response;
    }
}
