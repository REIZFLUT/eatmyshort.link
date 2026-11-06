<?php

declare(strict_types=1);

namespace App\Event\Link;

use App\Entity\Link;

/**
 * A link was written. Read-only; the transaction is committed.
 *
 * Typical use: notify a downstream system, warm a cache, enforce a post-creation
 * policy. Something going wrong here must not undo the link - queue the work or log it.
 *
 * @api
 *
 * @since 1.0
 */
final class LinkSavedEvent
{
    public function __construct(
        private readonly Link $link,
        private readonly bool $created,
    ) {
    }

    public function getLink(): Link
    {
        return $this->link;
    }

    public function wasCreated(): bool
    {
        return $this->created;
    }
}
