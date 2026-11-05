<?php

declare(strict_types=1);

namespace App\Tracking;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Counts unique visitors per day without storing who they are: the hash uses a
 * random salt that exists only for the current day and is never persisted in the
 * database. Once the salt expires, hashes from different days cannot be linked.
 */
final readonly class VisitorHasher
{
    public function __construct(private CacheItemPoolInterface $cacheApp)
    {
    }

    public function hash(?string $ip, ?string $userAgent, \DateTimeImmutable $at): string
    {
        return hash_hmac('sha256', ($ip ?? '').'|'.($userAgent ?? ''), $this->saltFor($at));
    }

    private function saltFor(\DateTimeImmutable $at): string
    {
        $item = $this->cacheApp->getItem('visitor_salt_'.$at->format('Ymd'));
        if (!$item->isHit()) {
            $item->set(bin2hex(random_bytes(32)));
            $item->expiresAt($at->setTime(0, 0)->modify('+2 days'));
            $this->cacheApp->save($item);
        }

        return (string) $item->get();
    }
}
