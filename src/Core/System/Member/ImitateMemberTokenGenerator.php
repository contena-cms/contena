<?php declare(strict_types=1);

namespace Contena\Core\System\Member;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Token\RegisteredClaims;
use Contena\Core\Framework\JWT\Channel\JWTGenerator;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Member\Struct\ImitateMemberToken;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * @extends JWTGenerator<ImitateMemberToken>
 */
class ImitateMemberTokenGenerator extends JWTGenerator
{
    /**
     * @internal
     */
    public function __construct(
        Configuration $configuration,
        DataValidator $validator,
    ) {
        parent::__construct($configuration, $validator);
    }

    protected function getJWTStructClass(): string
    {
        return ImitateMemberToken::class;
    }

    protected function getStructConstraints(): DataValidationDefinition
    {
        $definition = parent::getStructConstraints();
        $definition->add(RegisteredClaims::ISSUER, new NotBlank(), new NotNull());

        return $definition;
    }
}
