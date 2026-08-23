<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Core\Application;

/**
 * Used to invalidate the cached media urls from the reverse proxy
 * If you are using fastly as cdn, you should configure contena.cdn.fastly.enabled to true
 */
interface MediaReverseProxy
{
    public function enabled(): bool;

    /**
     * @param array<string> $urls
     */
    public function ban(array $urls): void;
}
