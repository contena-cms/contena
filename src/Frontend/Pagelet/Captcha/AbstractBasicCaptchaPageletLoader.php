<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Captcha;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractBasicCaptchaPageletLoader
{
    abstract public function load(Request $request, ChannelContext $context): BasicCaptchaPagelet;
}
