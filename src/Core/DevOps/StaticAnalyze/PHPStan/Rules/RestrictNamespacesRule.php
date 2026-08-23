<?php

declare(strict_types=1);

namespace Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * @internal
 */
class RestrictNamespacesRule
{
    private const string NAMESPACE_ADMINISTRATION = 'Contena\Administration';
    private const string NAMESPACE_CORE = 'Contena\Core';
    private const string NAMESPACE_ELASTICSEARCH = 'Contena\Elasticsearch';
    private const string NAMESPACE_FRONTEND = 'Contena\Frontend';

    #[TestRule]
    public function restrictNamespacesInAdministration(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ADMINISTRATION))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ELASTICSEARCH),
                Selector::inNamespace(self::NAMESPACE_FRONTEND),
            );
    }

    #[TestRule]
    public function restrictNamespacesInCore(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_CORE))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ADMINISTRATION),
                Selector::inNamespace(self::NAMESPACE_ELASTICSEARCH),
                Selector::inNamespace(self::NAMESPACE_FRONTEND),
            );
    }

    #[TestRule]
    public function restrictNamespacesInElasticsearch(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_ELASTICSEARCH))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ADMINISTRATION),
                Selector::inNamespace(self::NAMESPACE_FRONTEND),
            );
    }

    #[TestRule]
    public function restrictNamespacesInFrontend(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace(self::NAMESPACE_FRONTEND))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::inNamespace(self::NAMESPACE_ADMINISTRATION),
                Selector::inNamespace(self::NAMESPACE_ELASTICSEARCH),
            );
    }
}
