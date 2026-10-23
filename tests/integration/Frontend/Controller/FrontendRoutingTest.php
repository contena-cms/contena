<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Framework\Routing\Exception\InvalidRouteScopeException;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\Page\Navigation\NavigationPage;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class FrontendRoutingTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use RequestStackTestBehaviour;
    use SessionTestBehaviour;
    use FrontendControllerTestBehaviour;

    public function testForwardFromAddPromotionToHomePage(): void
    {
        $renderedParameters = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            FrontendRenderEvent::class,
            static function (FrontendRenderEvent $event) use (&$renderedParameters): void {
                $skippedViews = [
                    '@Frontend/frontend/layout/header.html.twig',
                    '@Frontend/frontend/layout/footer.html.twig',
                ];
                if (\in_array($event->getView(), $skippedViews, true)) {
                    return;
                }

                $renderedParameters[] = $event->getParameters();
            }
        );

        $response = $this->request(
            'POST',
            '/checkout/promotion/add',
            $this->tokenize('frontend.checkout.promotion.add', [
                'forwardTo' => 'frontend.home.page',
            ])
        );

        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $renderedParameters);
        $page = $renderedParameters[0]['page'];
        static::assertInstanceOf(NavigationPage::class, $page);
        static::assertInstanceOf(CmsPageEntity::class, $page->getCmsPage());
        static::assertSame('Default listing layout', $page->getCmsPage()->getName());
    }

    public function testForwardFromAddPromotionToApiFails(): void
    {
        $response = $this->request(
            'POST',
            '/checkout/promotion/add',
            $this->tokenize('frontend.checkout.promotion.add', [
                'forwardTo' => 'api.action.admin_user.admin_user-recovery.hash',
            ])
        );

        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertStringContainsString(InvalidRouteScopeException::class, $response->getContent());
    }
}
