<?php

declare(strict_types=1);

namespace Plugin\UtmTagging;

use App\Event\Redirect\RedirectResolvingEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Adds campaign parameters to the resolved redirect target.
 *
 * A RedirectResolvingEvent listener runs on the redirect hot path: the link is already
 * loaded, so the tagger does string work only - no queries, no network, no latency a visitor
 * would notice.
 */
#[AsEventListener]
final readonly class UtmTaggingListener
{
    public function __construct(private UtmTagger $tagger)
    {
    }

    public function __invoke(RedirectResolvingEvent $event): void
    {
        $event->setTarget($this->tagger->tag($event->getTarget()));
    }
}
