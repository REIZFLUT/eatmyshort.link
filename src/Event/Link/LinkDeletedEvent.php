<?php

declare(strict_types=1);

namespace App\Event\Link;

use App\Entity\Link;

/**
 * A link was deleted. Read-only.
 *
 * The entity is detached by the time this fires: use it to record what disappeared
 * (id, short code, target), never to read lazy associations.
 *
 * @api
 *
 * @since 1.0
 */
final class LinkDeletedEvent
{
    public function __construct(private readonly Link $link)
    {
    }

    public function getLink(): Link
    {
        return $this->link;
    }
}
