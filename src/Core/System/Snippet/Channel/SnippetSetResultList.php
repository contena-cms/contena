<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Channel;

use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
final class SnippetSetResultList extends Struct
{
    /**
     * @param list<SnippetSetResult> $sets
     */
    public function __construct(public array $sets)
    {
    }

    public function getApiAlias(): string
    {
        return 'snippet_set_result_list';
    }
}
