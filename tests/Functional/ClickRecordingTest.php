<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\EventTestState;
use App\Tracking\ClickRecorder;
use App\Tracking\VisitorHasher;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

/**
 * The uniqueness contract of the click path, driven through the real ClickRecorder
 * service the way kernel.terminate uses it. It pins the clock of every click so the
 * "one unique visitor per calendar day" rule can be observed across day boundaries,
 * which a plain HTTP request (always "now") cannot do.
 */
final class ClickRecordingTest extends EventTestCase
{
    private const string CHROME = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

    public function testAVisitorIsUniqueOnlyOncePerDayAndTheCounterGrows(): void
    {
        $owner = $this->createUser('owner@example.com');
        $link = $this->createLink($owner, $this->createDomain('go.example.com'), 'einmal', 'https://www.example.com/');
        $recorder = $this->pinnedRecorder();
        EventTestState::$recordClicks = true;

        $request = $this->clickRequest();
        $at = new \DateTimeImmutable('today 09:00:00');

        $recorder->record($request, (int) $link->getId(), $at);
        $recorder->record($request, (int) $link->getId(), $at);

        $recorded = EventTestState::recordedClicks();
        self::assertCount(2, $recorded);
        self::assertTrue($recorded[0]->isUniqueVisitor(), 'the first click of the day is a unique visitor');
        self::assertFalse($recorded[1]->isUniqueVisitor(), 'the same visitor_hash on the same day is no longer unique');

        $fresh = $this->fresh($link);
        self::assertSame(2, $fresh->getClickCount(), 'every recorded click increments the link counter by one');
        self::assertNotNull($fresh->getLastClickAt(), 'last_click_at is set on every recorded click');
    }

    public function testTheSameVisitorIsUniqueAgainOnTheNextDay(): void
    {
        $owner = $this->createUser('owner@example.com');
        $link = $this->createLink($owner, $this->createDomain('go.example.com'), 'neuer-tag', 'https://www.example.com/');
        $recorder = $this->pinnedRecorder();
        EventTestState::$recordClicks = true;

        $request = $this->clickRequest();
        $recorder->record($request, (int) $link->getId(), new \DateTimeImmutable('today 09:00:00'));
        $recorder->record($request, (int) $link->getId(), new \DateTimeImmutable('tomorrow 09:00:00'));

        $recorded = EventTestState::recordedClicks();
        self::assertCount(2, $recorded);
        self::assertTrue($recorded[0]->isUniqueVisitor(), 'day one');
        self::assertTrue($recorded[1]->isUniqueVisitor(), 'a fresh calendar day has its own visitor salt, so the visitor counts as new again');
        self::assertSame(2, $this->fresh($link)->getClickCount());
    }

    /**
     * The visitor hash is salted per calendar day from cache.app; pinning the hasher to its
     * own pool keeps the salt stable over the two calls of a test so uniqueness is observable.
     */
    private function pinnedRecorder(): ClickRecorder
    {
        static::getContainer()->set(VisitorHasher::class, new VisitorHasher(new ArrayAdapter()));

        return static::getContainer()->get(ClickRecorder::class);
    }

    private function clickRequest(): Request
    {
        return Request::create('http://go.example.com/x', 'GET', server: [
            'HTTP_USER_AGENT' => self::CHROME,
            'REMOTE_ADDR' => '203.0.113.10',
        ]);
    }
}
