<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cms;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Comment\AbstractBlogCommentLoader;
use Contena\Core\Content\Blog\Channel\Comment\BlogCommentsWidgetLoadedHook;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogDescriptionCommentsStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\System\SystemConfig\SystemConfigService;

class BlogDescriptionCommentsCmsElementResolver extends AbstractBlogDetailCmsElementResolver
{
    final public const TYPE = 'blog-description-comments';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractBlogCommentLoader $blogCommentLoader,
        private readonly ScriptExecutor $scriptExecutor,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $data = new BlogDescriptionCommentsStruct();
        $slot->setData($data);

        $blogConfig = $slot->getFieldConfig()->get('blog');
        if ($blogConfig === null) {
            return;
        }

        $request = $resolverContext->getRequest();
        $commentSuccess = (bool) RequestParamHelper::get($request, 'success', false);
        $data->setCommentSuccess($commentSuccess);

        $blog = null;

        if ($blogConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $blog = $this->resolveEntityValue($resolverContext->getEntity(), $blogConfig->getStringValue());
        }

        if ($blogConfig->isStatic()) {
            $blog = $this->getSlotBlog($slot, $result, $blogConfig->getStringValue());
        }

        if (!$blog instanceof ChannelBlogEntity) {
            // blog can not be resolved, so we do not enrich the slot
            return;
        }

        $data->setBlog($blog);
        $data->setBlogId($blog->getId());

        if ($this->systemConfigService->getBool('core.listing.showComment', $resolverContext->getChannelContext()->getChannelId())) {
            $comments = $this->blogCommentLoader->load($request, $resolverContext->getChannelContext(), $blog->getId(), $blog->getParentId());

            $this->scriptExecutor->execute(new BlogCommentsWidgetLoadedHook($comments, $resolverContext->getChannelContext()));

            $data->setComments($comments);
        }
    }
}
