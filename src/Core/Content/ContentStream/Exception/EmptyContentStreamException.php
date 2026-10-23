<?php declare(strict_types=1);

namespace Contena\Core\Content\ContentStream\Exception;


/**
 * Thrown when a blog stream is valid but has no filters left (api_filter is []).
 *
 * Extends {@see NoFilterException} on purpose so existing `catch (NoFilterException)` handlers
 * keep working, while callers can catch this narrower type to treat an emptied stream as "no filtering".
 *
 * @codeCoverageIgnore
 */
class EmptyContentStreamException extends NoFilterException
{
}
