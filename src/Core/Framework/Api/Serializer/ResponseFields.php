<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Serializer;

use Contena\Core\Framework\Api\ApiException;

class ResponseFields
{
    /**
     * @param array<string, list<string>>|null $includes
     * @param array<string, list<string>>|null $excludes
     */
    public function __construct(
        protected ?array $includes = null,
        protected ?array $excludes = null,
    ) {
        $this->validateFields();
    }

    public function isAllowed(string $type, string $property): bool
    {
        if (isset($this->excludes[$type]) && \in_array($property, $this->excludes[$type], true)) {
            return false;
        }

        if (isset($this->includes[$type])) {
            return \in_array($property, $this->includes[$type], true);
        }

        return true;
    }

    public function hasNested(string $alias, string $prefix): bool
    {
        $prefix .= '.';

        $excludeFields = $this->excludes[$alias] ?? [];
        foreach ($excludeFields as $property) {
            if (str_starts_with((string) $property, $prefix)) {
                return false;
            }
        }

        $includeFields = $this->includes[$alias] ?? [];
        foreach ($includeFields as $property) {
            if (str_starts_with((string) $property, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function validateFields(): void
    {
        if (\is_array($this->includes)) {
            foreach ($this->includes as $type => $fields) {
                if (\is_array($fields)) {
                    continue;
                }

                throw ApiException::invalidResponseFieldList('includes', $type, \gettype($fields));
            }
        }

        if (\is_array($this->excludes)) {
            foreach ($this->excludes as $type => $fields) {
                if (\is_array($fields)) {
                    continue;
                }

                throw ApiException::invalidResponseFieldList('excludes', $type, \gettype($fields));
            }
        }
    }
}
