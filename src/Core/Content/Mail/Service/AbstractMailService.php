<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;

/**
 * @phpstan-import-type MailData from AbstractMailFactory
 */
#[Package('after-sales')]
abstract class AbstractMailService
{
    abstract public function getDecorated(): AbstractMailService;

    /**
     * @param MailData $data
     * @param array<string, mixed> $templateData
     */
    abstract public function send(array $data, Context $context, array $templateData = []): ?Email;
}
