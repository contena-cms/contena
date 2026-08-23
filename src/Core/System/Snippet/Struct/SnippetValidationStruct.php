<?php declare(strict_types=1);

namespace Contena\Core\System\Snippet\Struct;

use Contena\Core\Framework\Struct\Struct;

class SnippetValidationStruct extends Struct
{
    public function __construct(
        public readonly MissingSnippetCollection $missingSnippets,
        public readonly InvalidPluralizationCollection $invalidPluralization,
    ) {
    }
}
