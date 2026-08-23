<?php declare(strict_types=1);

namespace Contena\Core\Content\Mail\Service;

use Contena\Core\Content\Mail\MailException;
use Contena\Core\Framework\Context;
use Symfony\Component\Mime\Email;

abstract class AbstractMailSender
{
    abstract public function getDecorated(): AbstractMailSender;

    /**
     * @throws MailException
     */
    abstract public function send(Email $email, Context $context): void;
}
