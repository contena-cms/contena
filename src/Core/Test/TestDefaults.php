<?php declare(strict_types=1);

namespace Contena\Core\Test;

/**
 * @internal
 * This class contains some defaults for test case
 */
class TestDefaults
{
    final public const string CHANNEL = '98432def39fc4624b33213a56b8c944d';
    final public const string FALLBACK_MEMBER_GROUP = 'cfbd5018d38d41d8adca10d94fc8bdd6';
    // use pre-hashed password, so we don't need to hash in every test, password is `contenaAdmin`
    final public const string HASHED_PASSWORD = '$2y$10$GE/0k/Kf7oA3fM3eoairZulev08poD8fKzK47Ct1n8/l50wD/BpRW';
}
