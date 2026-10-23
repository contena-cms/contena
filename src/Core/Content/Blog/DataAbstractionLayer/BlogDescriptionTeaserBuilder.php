<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer;

use Contena\Core\Framework\Util\HtmlSanitizer;

/**
 * Single source of truth for deriving the `descriptionTeaser` from a blog description: the
 * description is stripped of HTML via the configurable html_sanitizer (field key
 * `blog_translation.descriptionTeaser`, by default removing all tags) and then truncated.
 *
 * @internal
 */
class BlogDescriptionTeaserBuilder
{
    public const TEASER_FIELD = 'blog_translation.descriptionTeaser';

    private const MAX_LENGTH = 512;

    public function __construct(private readonly HtmlSanitizer $sanitizer)
    {
    }

    public function build(?string $description): ?string
    {
        if ($description === null || $description === '') {
            return $description;
        }

        $stripped = $this->sanitizer->sanitize($description, [], false, self::TEASER_FIELD);

        return mb_substr($stripped, 0, self::MAX_LENGTH);
    }
}
