<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Flag;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Api\Context\SystemSource;

class ApiAware extends Flag
{
    private const array BASE_URLS = [
        AdminApiSource::class => '/api/',
        ChannelApiSource::class => '/channel-api/',
    ];

    /**
     * @var array<string, string>
     */
    private array $allowList = [];

    public function __construct(string ...$protectedSources)
    {
        foreach ($protectedSources as $source) {
            $this->allowList[$source] = self::BASE_URLS[$source];
        }

        if ($protectedSources === []) {
            $this->allowList = self::BASE_URLS;
        }
    }

    public function isBaseUrlAllowed(string $baseUrl): bool
    {
        $baseUrl = rtrim($baseUrl, '/') . '/';

        foreach ($this->allowList as $url) {
            if (str_contains($baseUrl, $url)) {
                return true;
            }
        }

        return false;
    }

    public function isSourceAllowed(string $source): bool
    {
        if ($source === SystemSource::class) {
            return true;
        }

        if (isset($this->allowList[$source])) {
            return true;
        }

        $parentSources = class_parents($source);

        if (!$parentSources) {
            return false;
        }

        foreach ($parentSources as $parentSource) {
            if (isset($this->allowList[$parentSource])) {
                return true;
            }
        }

        return false;
    }

    public function parse(): \Generator
    {
        yield 'read_protected' => [
            array_keys($this->allowList),
        ];
    }
}
