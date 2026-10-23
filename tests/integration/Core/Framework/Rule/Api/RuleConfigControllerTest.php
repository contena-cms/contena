<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Rule\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Rule\UserGroupRule;

/**
 * @internal
 */
class RuleConfigControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testGetConditionsConfig(): void
    {
        $this->getBrowser()->request(
            'GET',
            '/api/_info/rule-config'
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        $userGroupRuleName = (new UserGroupRule())->getName();
        static::assertArrayHasKey($userGroupRuleName, $content);

        $userGroupRouleConfig = $content[$userGroupRuleName];

        static::assertCount(2, $userGroupRouleConfig['operatorSet']['operators']);
        static::assertSame(RuleConfig::OPERATOR_SET_STRING, $userGroupRouleConfig['operatorSet']['operators']);
        static::assertFalse($userGroupRouleConfig['operatorSet']['isMatchAny']);

        static::assertCount(1, $userGroupRouleConfig['fields']);

        static::assertSame('userGroupIds', $userGroupRouleConfig['fields']['userGroupIds']['name']);
        static::assertSame('multi-entity-id-select', $userGroupRouleConfig['fields']['userGroupIds']['type']);
        static::assertSame(UserGroupDefinition::ENTITY_NAME, $userGroupRouleConfig['fields']['userGroupIds']['config']['entity']);
    }
}
