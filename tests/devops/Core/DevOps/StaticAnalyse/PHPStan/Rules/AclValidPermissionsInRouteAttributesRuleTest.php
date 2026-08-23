<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\AclValidPermissionsHelper;
use Contena\Core\DevOps\StaticAnalyze\PHPStan\Rules\AclValidPermissionsInRouteAttributesRule;

/**
 * @internal
 *
 * @extends  RuleTestCase<AclValidPermissionsInRouteAttributesRule>
 */
class AclValidPermissionsInRouteAttributesRuleTest extends RuleTestCase
{
    private static ?AclValidPermissionsInRouteAttributesRule $rule = null;

    public static function setUpBeforeClass(): void
    {
        self::$rule = new AclValidPermissionsInRouteAttributesRule(new AclValidPermissionsHelper(__DIR__ . '/data/AclValidPermissionsRule/entity-schema.json'));
    }

    public static function tearDownAfterClass(): void
    {
        self::$rule = null;
    }

    #[RunInSeparateProcess]
    public function testRule(): void
    {
        // route attribute in controller
        $this->analyse([__DIR__ . '/data/AclValidPermissionsRule/InvalidAclRouteInRouteAttributeController.php'], [
            [
                'Permission "class-non-existing-permission" is not a valid backend ACL key. If it\'s an entity based permission, please check if entity is listed in the entity-schema.json. If it\'s a custom permissions, please check if it should be added to the allowlist.',
                1,
            ],
            [
                'Permission "system:create" is not a valid backend ACL key. If it\'s an entity based permission, please check if entity is listed in the entity-schema.json. If it\'s a custom permissions, please check if it should be added to the allowlist.',
                1,
            ],
            [
                'Permission "non-existing-permission" is not a valid backend ACL key. If it\'s an entity based permission, please check if entity is listed in the entity-schema.json. If it\'s a custom permissions, please check if it should be added to the allowlist.',
                1,
            ],
        ]);

        // attribute in non-controller - skip
        $this->analyse([__DIR__ . '/data/AclValidPermissionsRule/skipping-route-attribute-check-in-non-controller.php'], [
        ]);

        // attributes where acl name can't be extracted - skip
        $this->analyse([__DIR__ . '/data/AclValidPermissionsRule/attributes-to-be-skipped.php'], [
        ]);
    }

    /**
     * @return AclValidPermissionsInRouteAttributesRule
     */
    protected function getRule(): Rule
    {
        \assert(self::$rule instanceof AclValidPermissionsInRouteAttributesRule);

        return self::$rule;
    }
}
