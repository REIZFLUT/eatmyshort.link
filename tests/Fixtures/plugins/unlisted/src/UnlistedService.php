<?php

declare(strict_types=1);

namespace Plugin\Unlisted;

/**
 * Sits in a plugin directory that is not in the whitelist, so it is never autoloaded and
 * its namespace is never registered - not even when the app boots.
 */
final class UnlistedService
{
}
