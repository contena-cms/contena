<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\ChannelApiCustomFieldMapper;
use Contena\Core\System\CustomField\CustomFieldTypes;
use Contena\Core\System\Member\Channel\ChangeMemberProfileRoute;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ChangeMemberProfileRoute::class)]
class ChangeMemberProfileRouteTest extends TestCase
{
    public function testCustomFieldsGetPassed(): void
    {
        $customFields = new RequestDataBag(['test1' => '1', 'test2' => '2']);

        $memberRepository = $this->createMock(EntityRepository::class);
        $memberRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data): bool {
                static::assertSame(['test1' => '1'], $data[0]['customFields']);

                return true;
            }));

        $change = new ChangeMemberProfileRoute(
            $memberRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            $this->createValidationFactory(),
            new ChannelApiCustomFieldMapper(static::createStub(Connection::class), [
                'member' => [['name' => 'test1', 'type' => CustomFieldTypes::TEXT]],
            ]),
        );

        $member = new MemberEntity();
        $member->setId('member1');
        $data = new RequestDataBag([
            'customFields' => $customFields,
        ]);

        $change->change($data, Generator::generateChannelContext(member: $member), $member);
    }

    public function testProfileFieldsGetPassed(): void
    {
        $memberRepository = $this->createMock(EntityRepository::class);
        $memberRepository
            ->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $data): bool {
                static::assertCount(1, $data);
                static::assertIsArray($data[0]);
                static::assertSame('Contena Member', $data[0]['name']);
                static::assertSame('123456789', $data[0]['phoneNumber']);

                return true;
            }));

        $change = new ChangeMemberProfileRoute(
            $memberRepository,
            new EventDispatcher(),
            static::createStub(DataValidator::class),
            $this->createValidationFactory(),
            new ChannelApiCustomFieldMapper(static::createStub(Connection::class), []),
        );

        $member = new MemberEntity();
        $member->setId('member1');
        $data = new RequestDataBag([
            'name' => 'Contena Member',
            'phoneNumber' => '123456789',
        ]);

        $change->change($data, Generator::generateChannelContext(member: $member), $member);
    }

    private function createValidationFactory(): DataValidationFactoryInterface
    {
        $factory = static::createStub(DataValidationFactoryInterface::class);
        $factory->method('update')->willReturn(new DataValidationDefinition());

        return $factory;
    }
}
