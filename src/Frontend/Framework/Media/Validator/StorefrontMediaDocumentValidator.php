<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Media\Validator;

use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Framework\Media\StorefrontMediaValidatorInterface;
use Contena\Storefront\Framework\StorefrontFrameworkException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('discovery')]
class StorefrontMediaDocumentValidator implements StorefrontMediaValidatorInterface
{
    use MimeTypeValidationTrait;

    public function getType(): string
    {
        return 'documents';
    }

    public function validate(UploadedFile $file): void
    {
        $valid = $this->checkMimeType($file, [
            'pdf' => ['application/pdf', 'application/x-pdf'],
        ]);

        if (!$valid) {
            throw StorefrontFrameworkException::fileTypeNotAllowed((string) $file->getMimeType(), $this->getType());
        }
    }
}
