<?php

declare(strict_types=1);

namespace Plugin\Off;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Belongs to the disabled "off" plugin. If the disabled plugin were loaded, this service
 * would exist and the "off_plugin" route would resolve; the tests assert that neither does.
 */
final class OffController extends AbstractController
{
    public function index(): Response
    {
        return new Response('off');
    }
}
