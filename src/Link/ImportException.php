<?php

declare(strict_types=1);

namespace App\Link;

/**
 * Thrown when a whole import run cannot be processed at all (missing header,
 * no target column, too many rows). The message is a translation key, so the
 * controller can pass it straight to a flash message.
 */
final class ImportException extends \RuntimeException
{
}
