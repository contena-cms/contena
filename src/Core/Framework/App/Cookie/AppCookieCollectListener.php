<?php

declare(strict_types=1);

namespace Contena\Core\Framework\App\Cookie;

use Contena\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Contena\Core\Content\Cookie\Struct\CookieEntry;
use Contena\Core\Content\Cookie\Struct\CookieEntryCollection;
use Contena\Core\Content\Cookie\Struct\CookieGroup;
use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\Framework\App\Feature\AppFeatureStorage;

/**
 * @internal only for use by the app-system
 */
class AppCookieCollectListener
{
    public function __construct(private readonly AppFeatureStorage $storage)
    {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $features = $this->storage->forActiveApps(CookieConfig::class);

        foreach ($features as $feature) {
            $config = $feature->config;
            $this->addCookieGroup($event->cookieGroupCollection, $config, $config->entries);
        }
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private function addCookieGroup(CookieGroupCollection $cookieGroupCollection, CookieConfig $config, array $entries): void
    {
        $cookieGroup = $cookieGroupCollection->get($config->snippetName);
        if ($cookieGroup === null) {
            $cookieGroup = new CookieGroup($config->snippetName);
            $cookieGroupCollection->add($cookieGroup);
        }

        if ($config->snippetDescription !== null) {
            $cookieGroup->description = $config->snippetDescription;
        }

        if ($config->cookie !== null) {
            $cookieGroup->setCookie($config->cookie);
        }

        if ($config->value !== null) {
            $cookieGroup->value = $config->value;
        }

        if ($config->expiration !== null) {
            $cookieGroup->expiration = $config->expiration;
        }

        if ($config->entries === []) {
            return;
        }

        $cookieEntries = $cookieGroup->getEntries();
        if ($cookieEntries === null) {
            $cookieEntries = new CookieEntryCollection();
            $cookieGroup->setEntries($cookieEntries);
        }

        foreach ($entries as $entry) {
            $cookieEntry = new CookieEntry((string) $entry['cookie']);

            if (\array_key_exists('snippet_name', $entry)) {
                $cookieEntry->name = (string) $entry['snippet_name'];
            }

            if (\array_key_exists('snippet_description', $entry)) {
                $cookieEntry->description = (string) $entry['snippet_description'];
            }

            if (\array_key_exists('value', $entry)) {
                $cookieEntry->value = (string) $entry['value'];
            }

            if (\array_key_exists('expiration', $entry)) {
                $cookieEntry->expiration = (int) $entry['expiration'];
            }

            $cookieEntries->add($cookieEntry);
        }
    }
}
