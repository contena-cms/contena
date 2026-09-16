<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\ContentSystem\DataLoader;

use Contena\Core\Content\Blog\Channel\Comment\AbstractBlogCommentLoader;
use Contena\Core\Content\Blog\Channel\Comment\BlogCommentResult;
use Contena\Core\Framework\ContenaHttpException;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<BlogCommentResult>
 */
final class BlogCommentDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'blog_comments';

    public function __construct(private readonly AbstractBlogCommentLoader $commentLoader)
    {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, hasDefault: true, default: 'blogId'),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        ChannelContext $context,
        Request $request,
    ): ContentDataLoaderResult {
        $blogId = $inputs->stringOrNull('property');
        if ($blogId === null || !Uuid::isValid($blogId)) {
            return ContentDataLoaderResult::notFound();
        }

        try {
            return ContentDataLoaderResult::uncacheable($this->commentLoader->load($request, $context, $blogId));
        } catch (ContenaHttpException) {
            return ContentDataLoaderResult::notFound();
        }
    }
}
