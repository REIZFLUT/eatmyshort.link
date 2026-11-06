<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Click;

/**
 * ClickRepository::deleteOlderThan must keep working with a chunked delete that is portable
 * across SQLite and MariaDB/MySQL. The old statement used SQLite's rowid inside a subquery
 * on the same table, which MySQL rejects outright.
 */
final class ClickPruneTest extends AppTestCase
{
    public function testOnlyClicksOlderThanTheCutoffAreDeletedAcrossSeveralChunks(): void
    {
        $owner = $this->createUser('owner@example.com');
        $link = $this->createLink($owner, $this->createDomain('go.example.com'), 'prune', 'https://www.example.com/');
        $em = $this->em();

        $today = new \DateTimeImmutable('today');
        $oldDay = $today->modify('-30 days')->setTime(12, 0);
        for ($i = 0; $i < 5; ++$i) {
            $em->persist(new Click($link, $oldDay, 'old-'.$i));
        }
        for ($i = 0; $i < 3; ++$i) {
            $em->persist(new Click($link, $today->setTime(12, 0), 'new-'.$i));
        }
        $em->flush();

        $cutoff = $today->modify('-7 days');
        // A chunk size smaller than the number of old rows forces more than one pass:
        // 5 rows with a limit of 2 are removed as 2 + 2 + 1.
        $deleted = $em->getRepository(Click::class)->deleteOlderThan($cutoff, 2);

        self::assertSame(5, $deleted, 'all old clicks are deleted even though one chunk cannot hold them');
        self::assertSame(0, $this->countClicks('WHERE clicked_day < ?', [$cutoff->format('Y-m-d')]), 'no click older than the cutoff survives');
        self::assertSame(3, $this->countClicks(), 'the newer clicks are untouched');

        // Nothing left to do on a second run.
        self::assertSame(0, $em->getRepository(Click::class)->deleteOlderThan($cutoff, 2));
    }

    /** @param list<string> $params */
    private function countClicks(string $where = '', array $params = []): int
    {
        return (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM click '.$where, $params);
    }
}
