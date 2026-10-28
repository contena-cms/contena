<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class DomainVerificationRequestStruct extends Struct
{
    protected string $fileName;

    #[ParameterNameChange(version: 'v6.8.0', parameterName: 'filename', newName: 'fileName', description: 'Will also become a promoted property.')]
    public function __construct(
        protected string $content,
        string $filename,
    ) {
        $this->fileName = $filename;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getApiAlias(): string
    {
        return 'store_domain_verification_request';
    }
}
