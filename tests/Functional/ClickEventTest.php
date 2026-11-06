<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Click;
use App\Entity\Link;
use App\Tests\Support\EventTestState;
use App\Tracking\VisitorHasher;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The ClickRecordingEvent / ClickRecordedEvent contract. Both run in kernel.terminate,
 * after the visitor already got their redirect.
 */
final class ClickEventTest extends EventTestCase
{
    private const string CHROME = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

    public function testWithoutAListenerTheClickIsStillRecorded(): void
    {
        $owner = $this->createUser('owner@example.com');
        $link = $this->createLink($owner, $this->createDomain('go.example.com'), 'baseline', 'https://www.example.com/');

        $this->shortRequest('go.example.com', '/baseline', ['User-Agent' => self::CHROME]);

        self::assertResponseRedirects('https://www.example.com/', 302);
        $link = $this->fresh($link);
        self::assertSame(1, $link->getClickCount(), 'baseline: tracking is unaffected');
        self::assertCount(1, $this->em()->getRepository(Click::class)->findBy(['link' => $link]));
    }

    public function testVetoedClickIsNotWrittenAndNotCounted(): void
    {
        $owner = $this->createUser('owner@example.com');
        $link = $this->createLink($owner, $this->createDomain('go.example.com'), 'kein-klick', 'https://www.example.com/');

        EventTestState::$clickVetoReason = 'internal traffic';
        $logger = $this->captureLogs();

        $this->shortRequest('go.example.com', '/kein-klick', ['User-Agent' => self::CHROME]);

        // The visitor still gets their redirect, the click just never happens.
        self::assertResponseRedirects('https://www.example.com/', 302);
        $link = $this->fresh($link);
        self::assertSame(0, $link->getClickCount());
        self::assertCount(0, $this->em()->getRepository(Click::class)->findBy(['link' => $link]));
        self::assertTrue($logger->hasRecord('info', 'veto'), 'the veto reason must reach the log');
    }

    public function testRecordedEventCarriesTheDeterminedValues(): void
    {
        $owner = $this->createUser('owner@example.com');
        $link = $this->createLink($owner, $this->createDomain('go.example.com'), 'ereignis', 'https://www.example.com/');
        EventTestState::$recordClicks = true;
        $this->pinVisitorIdentity();

        $this->shortRequest('go.example.com', '/ereignis', ['User-Agent' => self::CHROME]);
        self::assertResponseRedirects();

        $recordedClicks = EventTestState::recordedClicks();
        self::assertCount(1, $recordedClicks);
        $recorded = $recordedClicks[0];
        self::assertSame('ereignis', $recorded->getLink()->getCode());

        $click = $this->em()->getRepository(Click::class)->findOneBy(['link' => $link]);
        self::assertNotNull($click);
        self::assertSame($click->getDeviceType(), $recorded->getDeviceType());
        self::assertSame($click->getCountryCode(), $recorded->getCountryCode());
        self::assertSame($click->getClickedAt()->format('Y-m-d H:i:s'), $recorded->getOccurredAt()->format('Y-m-d H:i:s'));
        self::assertTrue($recorded->isUniqueVisitor(), 'the first click of this visitor is unique');

        // A second click of the same visitor on the same day is stored but no longer unique.
        EventTestState::$recordedClicks = [];
        $this->shortRequest('go.example.com', '/ereignis', ['User-Agent' => self::CHROME]);
        $secondClicks = EventTestState::recordedClicks();
        self::assertCount(1, $secondClicks);
        self::assertFalse($secondClicks[0]->isUniqueVisitor());

        $fresh = $this->fresh($link);
        self::assertSame(2, $fresh->getClickCount());
        self::assertCount(2, $this->em()->getRepository(Click::class)->findBy(['link' => $fresh]));
    }

    /**
     * The test kernel resets cache.app between requests, which would regenerate the daily
     * visitor salt and make every request look like a new visitor. A hasher backed by its
     * own pool keeps the visitor identity stable so uniqueness can be observed.
     */
    private function pinVisitorIdentity(): void
    {
        static::getContainer()->set(VisitorHasher::class, new VisitorHasher(new ArrayAdapter()));
    }

    public function testVetoedClickOnAnotherLinkStillCountsTheFirst(): void
    {
        $owner = $this->createUser('owner@example.com');
        $domain = $this->createDomain('go.example.com');
        $counted = $this->createLink($owner, $domain, 'zaehlt', 'https://www.example.com/');
        $vetoed = $this->createLink($owner, $domain, 'zaehlt-nicht', 'https://www.example.com/');

        $this->shortRequest('go.example.com', '/zaehlt', ['User-Agent' => self::CHROME]);

        EventTestState::$clickVetoReason = 'no thanks';
        $this->shortRequest('go.example.com', '/zaehlt-nicht', ['User-Agent' => self::CHROME]);

        self::assertSame(1, $this->fresh($counted)->getClickCount());
        self::assertSame(0, $this->fresh($vetoed)->getClickCount());
        self::assertCount(0, $this->em()->getRepository(Click::class)->findBy(['link' => $this->em()->find(Link::class, $vetoed->getId())]));
    }
}
