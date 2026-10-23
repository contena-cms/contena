<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Service;

use Contena\Core\Content\Mail\MailException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;

abstract class AbstractMailSender
{
    abstract public function getDecorated(): AbstractMailSender;

    /**
     * @throws MailException
     */
    abstract public function send(Email $email): void;
}
