<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Media\Validator;

use Contena\Frontend\Framework\FrontendFrameworkException;
use Contena\Frontend\Framework\Media\FrontendMediaValidatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FrontendMediaDocumentValidator implements FrontendMediaValidatorInterface
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
            throw FrontendFrameworkException::fileTypeNotAllowed((string) $file->getMimeType(), $this->getType());
        }
    }
}
