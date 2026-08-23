<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Validation;

use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class MemberValidationFactory implements DataValidationFactoryInterface
{
    /**
     * @internal
     */
    public function __construct(
        /**
         * @todo seems to be the usecase for the contena api - import or so. maybe rename to MemberImportValidationService
         */
        private readonly DataValidationFactoryInterface $profileValidation
    ) {
    }

    public function create(ChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('member.create');

        $profileDefinition = $this->profileValidation->create($context);

        $this->merge($definition, $profileDefinition);

        $this->addConstraints($definition);

        return $definition;
    }

    public function update(ChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('member.update');

        $profileDefinition = $this->profileValidation->update($context);

        $this->merge($definition, $profileDefinition);

        $this->addConstraints($definition);

        return $definition;
    }

    private function addConstraints(DataValidationDefinition $definition): void
    {
        $definition
            ->add('email', new NotBlank(), new Email(null, 'VIOLATION::INVALID_EMAIL_FORMAT_ERROR'))
            ->add('active', new Type('boolean'));
    }

    /**
     * merges constraints from the second definition into the first validation definition
     */
    private function merge(DataValidationDefinition $definition, DataValidationDefinition $profileDefinition): void
    {
        foreach ($profileDefinition->getProperties() as $key => $constraints) {
            $definition->add($key, ...$constraints);
        }
    }
}
