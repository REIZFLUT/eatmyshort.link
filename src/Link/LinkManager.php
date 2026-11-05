<?php

declare(strict_types=1);

namespace App\Link;

use App\Entity\Link;
use App\Settings\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Shared create/update logic for the web UI and the API.
 */
final class LinkManager
{
    /**
     * Links whose title was already handled in this request. A WeakMap keeps the marker
     * tied to the entity instance without pinning it in memory; prepare() runs twice per
     * create (form POST_SUBMIT and validate()), and the second call must not hit the
     * network again.
     *
     * @var \WeakMap<Link, bool>
     */
    private \WeakMap $titleFetched;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ValidatorInterface $validator,
        private readonly ShortCodeGenerator $codes,
        private readonly Settings $settings,
        private readonly LinkTitleFetcher $titleFetcher,
    ) {
        $this->titleFetched = new \WeakMap();
    }

    public function applyDefaults(Link $link): void
    {
        if (!\in_array($link->getRedirectType(), Link::REDIRECT_TYPES, true) || null === $link->getId()) {
            $link->setRedirectType($this->settings->getInt(Settings::DEFAULT_REDIRECT));
        }
    }

    /** Fills in a random code when none was given. Call before validation. */
    public function prepare(Link $link): void
    {
        if ('' === $link->getCode() && null !== $link->getDomain()) {
            $link->setCode($this->codes->generate($link->getDomain()));
        }
        $this->fetchTitle($link);
    }

    public function validate(Link $link): ConstraintViolationListInterface
    {
        $this->prepare($link);

        return $this->validator->validate($link);
    }

    public function save(Link $link): void
    {
        $link->touch();
        if (null === $link->getId()) {
            $this->em->persist($link);
        }
        $this->em->flush();
    }

    public function delete(Link $link): void
    {
        $this->em->remove($link);
        $this->em->flush();
    }

    /**
     * Pulls the page title from the target when enabled – YOURLS-style – but only for a
     * blank title and an http(s) target, and never more than once per request. Any error
     * inside the fetcher stays contained: a failing fetch must not block link creation.
     */
    private function fetchTitle(Link $link): void
    {
        if (isset($this->titleFetched[$link])) {
            return;
        }
        if (!$this->settings->getBool(Settings::LINK_FETCH_TITLE)) {
            return;
        }
        $title = $link->getTitle();
        if (null !== $title && '' !== trim($title)) {
            return;
        }
        $target = $link->getTargetUrl();
        if (1 !== preg_match('#^https?://#i', $target)) {
            return;
        }

        $this->titleFetched[$link] = true;
        try {
            $fetched = $this->titleFetcher->fetch($target);
        } catch (\Throwable) {
            return;
        }
        if (null !== $fetched) {
            $link->setTitle($fetched);
        }
    }
}
