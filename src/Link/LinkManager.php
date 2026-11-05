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
final readonly class LinkManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private ValidatorInterface $validator,
        private ShortCodeGenerator $codes,
        private Settings $settings,
    ) {
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
}
