<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrlTemplate;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Dispatched when a `seo_url_template` row changes its `template` field, so the
 * corresponding SEO URLs are regenerated asynchronously and large content
 * iteration never blocks the administration save.
 *
 * Each message covers a single batch of entities; the handler dispatches a
 * follow-up message carrying the iterator offset until the whole entity set is
 * processed, so a single message never exceeds worker time limits.
 *
 * @internal
 */
class SeoUrlTemplateIndexingMessage implements AsyncMessageInterface
{
    /**
     * @param array{offset: int|null}|null $offset
     */
    public function __construct(
        public readonly string $routeName,
        public readonly string $entityName,
        public readonly ?array $offset = null,
        private readonly ?Context $context = null,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context ?? Context::createCLIContext();
    }
}
