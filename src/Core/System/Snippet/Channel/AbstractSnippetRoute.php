<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load the resolved snippets (translations) of the authenticated channel.
 * The language is taken from the `ct-language-id` header. Optionally the `prefixes` query parameter limits
 * the result to the given namespaces and the `languageIds` query parameter loads multiple languages at once.
 */
abstract class AbstractSnippetRoute
{
    abstract public function load(Request $request, ChannelContext $context): SnippetRouteResponse;

    abstract protected function getDecorated(): AbstractSnippetRoute;
}
