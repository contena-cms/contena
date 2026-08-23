<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Robots\Struct;

use Contena\Core\Framework\Struct\Struct;
use Contena\Frontend\Page\Robots\Parser\ParsedRobots;

class DomainRuleStruct extends Struct
{
    /**
     * @var list<RobotsDirective>
     */
    private array $directives = [];

    public function __construct(ParsedRobots $rules, private readonly string $basePath)
    {
        $this->initializeFromParsed($rules);
    }

    /**
     * @return list<RobotsDirective>
     */
    public function getDirectives(): array
    {
        return $this->directives;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    private function initializeFromParsed(ParsedRobots $parsed): void
    {
        foreach ($parsed->orphanedPathDirectives as $directive) {
            $directiveWithPath = $directive->withBasePath($this->basePath);
            $this->directives[] = $directiveWithPath;
        }
    }
}
