<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Price;

use Contena\Core\Content\Blog\Hook\Pricing\BlogPricingHook;
use Contena\Core\Content\Blog\Hook\Pricing\BlogProxy;
use Contena\Core\System\Channel\ChannelContext;

class AppScriptBlogPriceCalculator extends AbstractBlogPriceCalculator
{

    public function getDecorated(): AbstractBlogPriceCalculator
    {
        return $this->decorated;
    }

    public function calculate(iterable $blogs, ChannelContext $context): void
    {
        $this->decorated->calculate($blogs, $context);

        $proxies = [];
        foreach ($blogs as $blog) {
            $proxies[$blog->get('id')] = new BlogProxy($blog, $context, $this->priceStubs);
        }

        $this->scriptExecutor->execute(new BlogPricingHook($proxies, $context));
    }
}
