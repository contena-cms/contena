<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryCollection;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;
use Contena\Core\System\Member\MemberException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class MemberRecoveryIsExpiredRoute extends AbstractMemberRecoveryIsExpiredRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<MemberRecoveryCollection> $memberRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $memberRecoveryRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly ClockInterface $clock,
    ) {
    }

    public function getDecorated(): AbstractMemberRecoveryIsExpiredRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/account/member-recovery-is-expired', name: 'channel-api.account.member.recovery.is.expired', methods: ['POST'])]
    public function load(RequestDataBag $data, ChannelContext $context): MemberRecoveryIsExpiredResponse
    {
        $this->validateHash($data, $context);

        $hash = $data->get('hash');

        $memberHashCriteria = new Criteria();
        $memberHashCriteria->addFilter(new EqualsFilter('hash', $hash));

        $memberRecovery = $this->memberRecoveryRepository->search($memberHashCriteria, $context->getContext())->getEntities()->first();
        if (!$memberRecovery) {
            throw MemberException::memberNotFoundByHash($hash);
        }

        return new MemberRecoveryIsExpiredResponse($this->isExpired($memberRecovery));
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateHash(DataBag $data, ChannelContext $context): void
    {
        $definition = new DataValidationDefinition('member.recovery.get');

        $hashLength = 32;

        $definition->add('hash', new NotBlank(), new Type('string'), new Length($hashLength));

        $this->dispatchValidationEvent($definition, $data, $context->getContext());

        $this->validator->validate($data->all(), $definition);
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    private function isExpired(MemberRecoveryEntity $memberRecovery): bool
    {
        $validDateTime = $this->clock->now()->sub(new \DateInterval('PT2H'));

        return $validDateTime > $memberRecovery->getCreatedAt();
    }
}
