<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\AclValidPermissionsHelper;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\AclValidPermissionsInMethodRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<AclValidPermissionsInMethodRule>
 */
class AclValidPermissionsInMethodRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/AclValidPermissionsRule/invalid-acl-name-in-method-call.php'], [
            [
                'Permission "non-existing-permission!" is not a valid backend ACL key. If it\'s an entity based permission, please check if entity is listed in the entity-schema.json. If it\'s a custom permissions, please check if it should be added to the allowlist.',
                07,
            ],
        ]);
    }

    /**
     * @return AclValidPermissionsInMethodRule
     */
    protected function getRule(): Rule
    {
        return new AclValidPermissionsInMethodRule(new AclValidPermissionsHelper(__DIR__ . '/data/AclValidPermissionsRule/entity-schema.json'));
    }
}
