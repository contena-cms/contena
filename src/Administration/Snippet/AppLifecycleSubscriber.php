<?php declare(strict_types=1);

namespace Contena\Administration\Snippet;

use Contena\Core\Framework\App\Event\AppInstalledEvent;
use Contena\Core\Framework\App\Event\AppUpdatedEvent;
use Contena\Core\Framework\App\Source\SourceResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
readonly class AppLifecycleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private SourceResolver $sourceResolver,
        private AppAdministrationSnippetPersister $appAdministrationSnippetPersister,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [AppInstalledEvent::class => 'onAppUpdate', AppUpdatedEvent::class => 'onAppUpdate'];
    }

    public function onAppUpdate(AppInstalledEvent|AppUpdatedEvent $event): void
    {
        $app = $event->getApp();
        $fs = $this->sourceResolver->filesystemForApp($app);
        $snippets = [];
        if ($fs->has('Resources/app/administration/snippet')) {
            foreach ($fs->findFiles('*.json', 'Resources/app/administration/snippet') as $file) {
                $snippets[$file->getFilenameWithoutExtension()] = $file->getContents();
            }
        }
        $this->appAdministrationSnippetPersister->updateSnippets($app, $snippets, $event->getContext());
    }
}
