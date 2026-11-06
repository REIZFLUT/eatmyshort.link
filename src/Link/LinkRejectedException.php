<?php

declare(strict_types=1);

namespace App\Link;

/**
 * A plugin refused to persist a link (LinkPersistingEvent::reject()).
 *
 * Carries the message the plugin wants the caller to see: a form error in the web UI,
 * a 422 body in the API.
 *
 * @api
 *
 * @since 1.0
 */
final class LinkRejectedException extends \RuntimeException
{
}
