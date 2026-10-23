<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<NoContentResponse>
 */
final class BlogReviewSaveRouteExtension extends Extension
{
    public const NAME = 'blog-review-save-route.save';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $blogId,
        public readonly RequestDataBag $data,
        public readonly ChannelContext $context,
    ) {
    }
}
