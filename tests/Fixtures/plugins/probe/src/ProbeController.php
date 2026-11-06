<?php

declare(strict_types=1);

namespace Plugin\Probe;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Target of the route declared in config/routes.yaml. Extends AbstractController so the
 * framework's autoconfiguration tags it as a controller service.
 */
final class ProbeController extends AbstractController
{
    public function __construct(private readonly ProbeRecorder $recorder)
    {
    }

    public function index(): Response
    {
        $this->recorder->record();

        return new Response('probe:'.$this->recorder->calls(), Response::HTTP_OK, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
