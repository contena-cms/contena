<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Channel;

use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 *
 * @experimental stableVersion:v6.8.0 feature:STORE_API_SNIPPETS
 */
final class SnippetSetResultList extends Struct
{
    /**
     * @var list<SnippetSetResult>
     */
    public array $sets;

    public function __construct(SnippetSetResult ...$sets)
    {
        $this->sets = array_values($sets);
    }

    public function getApiAlias(): string
    {
        return 'snippet_set_result_list';
    }
}
