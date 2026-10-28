<?php declare(strict_types=1);

use Contena\Core\Framework\Deprecation\ClassAliasRegistry;

/**
 * Runs while Composer initializes, before PHPUnit can start collecting coverage.
 *
 * @codeCoverageIgnore
 */
ClassAliasRegistry::registerAliases(ClassAliasRegistry::ALIASES);
