<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Media\Validator;

use Contena\Frontend\Framework\FrontendFrameworkException;
use Contena\Frontend\Framework\Media\FrontendMediaValidatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FrontendMediaImageValidator implements FrontendMediaValidatorInterface
{
    use MimeTypeValidationTrait;

    public function getType(): string
    {
        return 'images';
    }

    public function validate(UploadedFile $file): void
    {
        $valid = $this->checkMimeType($file, [
            'jpe|jpg|jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
        ]);

        if (!$valid) {
            throw FrontendFrameworkException::fileTypeNotAllowed($file->getMimeType() ?? '', $this->getType());
        }

        // additional mime type validation
        // we detect the mime type over the `getimagesize` extension
        $imageSize = getimagesize($file->getPath() . '/' . $file->getFilename());
        if (!isset($imageSize['mime']) || $imageSize['mime'] !== $file->getMimeType()) {
            throw FrontendFrameworkException::fileTypeNotAllowed($file->getMimeType() ?? '', $this->getType());
        }
    }
}
