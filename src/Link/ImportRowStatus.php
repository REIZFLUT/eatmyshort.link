<?php

declare(strict_types=1);

namespace App\Link;

/** Outcome of a single CSV row in the import preview/summary. */
enum ImportRowStatus: string
{
    case Ok = 'ok';
    case Error = 'error';
    case Duplicate = 'duplicate';
}
