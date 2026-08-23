<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\ChannelRequest;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Storybook\StorybookService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * @internal
 */
class StorybookController extends AbstractController
{
    private const string BASE_TEMPLATE = <<<TWIG
        {% set assets = theme_config('assets.css') %}
        {% for file in assets %}
            <link rel="stylesheet"
                href="{{ asset(file, 'theme') }}">
        {% endfor %}
        {{ component(componentName, componentProps) }}
    TWIG;

    public function __construct(
        private readonly Environment $twig,
        private readonly StorybookService $storybookService,
    ) {
    }

    /**
     * @phpstan-ignore contena.routeScope (Not a real Frontend controller, only used in dev envs)
     */
    #[Route(
        path: '/storybook/{component}',
        name: 'storybook.component',
        env: 'dev',
        defaults: ['auth_required' => false],
        methods: [Request::METHOD_GET],
    )]
    public function storybook(string $component, Request $request): Response
    {
        $storybookDomain = (string) EnvironmentHelper::getVariable('STORYBOOK_DOMAIN', 'http://localhost:6006');

        if ($request->headers->get('Origin') !== $storybookDomain) {
            throw new NotFoundHttpException();
        }

        $channelContext = $this->storybookService->createChannelContext();
        $channelId = $channelContext->getChannelId();
        $themeId = $this->storybookService->getThemeId($channelId);

        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_ID, $themeId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, $channelId);

        try {
            $componentProps = $this->storybookService->resolveComponentProps($request, $channelContext);

            $content = $this->twig->render(
                $this->twig->createTemplate(self::BASE_TEMPLATE),
                [
                    'componentName' => $component,
                    'componentProps' => $componentProps,
                    'context' => $channelContext,
                    'themeId' => $themeId,
                ]
            );

            $response = new Response($content);
        } catch (RuntimeError|SyntaxError $e) {
            $response = new Response(
                '<div style="color: red; padding: 20px;">Template error: ' . htmlspecialchars($e->getMessage()) . '</div>',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $response->headers->set('Access-Control-Allow-Origin', $storybookDomain);

        return $response;
    }
}
