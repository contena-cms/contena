<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage;

use Contena\Core\Content\LandingPage\Aggregate\LandingPageChannel\LandingPageChannelDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PostWriteValidationEvent;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
class LandingPageValidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PostWriteValidationEvent::class => 'preValidate',
        ];
    }

    public function preValidate(PostWriteValidationEvent $event): void
    {
        $writeException = $event->getExceptions();
        $violationList = new ConstraintViolationList();

        foreach ($event->getCommandsForEntity(LandingPageDefinition::ENTITY_NAME) as $command) {
            if (!$command instanceof InsertCommand) {
                continue;
            }

            if (!$this->hasAnotherValidCommand($event, $command)) {
                $violationList->addAll(
                    $this->validator->startContext()
                        ->atPath($command->getPath() . '/channels')
                        ->validate(null, [new NotBlank()])
                        ->getViolations()
                );
                $writeException->add(new WriteConstraintViolationException($violationList));
            }
        }
    }

    private function hasAnotherValidCommand(PostWriteValidationEvent $event, WriteCommand $command): bool
    {
        $isValid = false;
        foreach ($event->getCommandsForEntity(LandingPageChannelDefinition::ENTITY_NAME) as $searchCommand) {
            if ($searchCommand instanceof InsertCommand) {
                $searchPrimaryKey = $searchCommand->getPrimaryKey();
                $searchLandingPageId = $searchPrimaryKey['landing_page_id'] ?? null;

                $currentPrimaryKey = $command->getPrimaryKey();
                $currentLandingPageId = $currentPrimaryKey['id'] ?? null;

                if ($searchLandingPageId === $currentLandingPageId) {
                    $isValid = true;
                }
            }
        }

        return $isValid;
    }
}
