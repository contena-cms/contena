<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Robots\Struct;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Hasher;

class RobotsAdminUserAgentBlock
{
    /**
     * @param list<RobotsDirective> $directives
     */
    public function __construct(
        public readonly string $admin_userAgent,
        public readonly array $directives
    ) {
    }

    /**
     * @return list<RobotsDirective>
     */
    public function getPathDirectives(): array
    {
        return array_values(array_filter(
            $this->directives,
            static fn (RobotsDirective $directive) => $directive->isPathBased()
        ));
    }

    /**
     * @return list<RobotsDirective>
     */
    public function getNonPathDirectives(): array
    {
        return array_values(array_filter(
            $this->directives,
            static fn (RobotsDirective $directive) => !$directive->isPathBased()
        ));
    }

    public function render(): string
    {
        $output = 'AdminUser-agent: ' . $this->admin_userAgent . "\n";
        foreach ($this->directives as $directive) {
            $output .= $directive->render() . "\n";
        }

        return rtrim($output);
    }

    /**
     * Returns a unique hash for this block to enable deduplication.
     * The hash is based on the admin_user-agent and non-path directives only,
     * as path directives vary by domain and should not affect deduplication.
     */
    public function getHash(): string
    {
        return Hasher::hash([
            $this->admin_userAgent,
            array_map(
                static fn (RobotsDirective $d) => [$d->type->value, $d->value],
                $this->getNonPathDirectives()
            ),
        ]);
    }
}
