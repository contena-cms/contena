<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Provider;

use Contena\Core\Content\Sitemap\Struct\Url;
use Contena\Core\Content\Sitemap\Struct\UrlResult;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Clock\Clock;

class HomeUrlProvider extends AbstractUrlProvider
{
    final public const CHANGE_FREQ = 'daily';
    final public const PRIORITY = 1.0;

    public function getDecorated(): AbstractUrlProvider
    {
        throw new DecorationPatternException(self::class);
    }

    public function getName(): string
    {
        return 'home';
    }

    /**
     * {@inheritdoc}
     */
    public function getUrls(ChannelContext $context, int $limit, ?int $offset = null): UrlResult
    {
        $homepageUrl = new Url();
        $homepageUrl->setLoc('');
        $homepageUrl->setLastmod(Clock::get()->now());
        $homepageUrl->setChangefreq(self::CHANGE_FREQ);
        $homepageUrl->setPriority(self::PRIORITY);
        $homepageUrl->setResource($this->getName());
        $homepageUrl->setIdentifier('');

        return new UrlResult([$homepageUrl], null);
    }
}
