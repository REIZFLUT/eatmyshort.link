<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Event\Admin\DashboardWidgetsEvent;
use Plugin\Off\OffController;
use Plugin\Probe\ProbeRecorder;
use Plugin\Probe\ProbeSettings;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Proves that the fixture plugin under tests/Fixtures/plugins/probe is really loaded: its
 * service is in the container, its route resolves, its listener sees a core event - and
 * that the disabled "off" plugin is ignored completely.
 */
final class PluginLoadingTest extends AppTestCase
{
    public function testEnabledPluginsRouteResolvesInTheContainer(): void
    {
        $this->client->loginUser($this->createUser('plugin@example.com'));

        // @phpstan-ignore symfonyContainer.serviceNotFound (the plugin only exists in the test container)
        $recorder = static::getContainer()->get(ProbeRecorder::class);
        self::assertInstanceOf(ProbeRecorder::class, $recorder);
        $before = $recorder->calls();

        $this->client->request('GET', '/__probe/plugin');

        self::assertResponseIsSuccessful();
        // The controller reaches the very same shared recorder instance.
        self::assertSame('probe:'.($before + 1), $this->client->getResponse()->getContent());
    }

    public function testEnabledPluginsListenerIsWiredToACoreEvent(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $event = new DashboardWidgetsEvent();
        $dispatcher->dispatch($event);

        self::assertTrue($event->has('probe'), 'the plugin added its dashboard widget');
    }

    public function testDisabledPluginRegistersNeitherServiceNorRoute(): void
    {
        self::assertFalse(class_exists(OffController::class), 'a disabled plugin must not even be autoloaded');

        $router = static::getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);
        self::assertNull($router->getRouteCollection()->get('off_plugin'));
        self::assertNotNull($router->getRouteCollection()->get('probe_plugin'));
    }

    /**
     * The settings written in the whitelist reach the container as the parameter
     * app.plugins.<name>.settings - the contract a plugin injects from its own services.yaml.
     */
    public function testPluginSettingsReachTheContainerAsAParameter(): void
    {
        self::assertSame(
            ['greeting' => 'hello'],
            static::getContainer()->getParameter('app.plugins.probe.settings'),
        );
    }

    /**
     * ...and a plugin service can actually consume it: the fixture service is autowired from
     * the plugin's services.yaml with '%app.plugins.probe.settings%'.
     */
    public function testPluginServiceCanInjectItsOwnSettings(): void
    {
        // @phpstan-ignore symfonyContainer.serviceNotFound (the plugin only exists in the test container)
        $settings = static::getContainer()->get(ProbeSettings::class);

        self::assertInstanceOf(ProbeSettings::class, $settings);
        self::assertSame(['greeting' => 'hello'], $settings->settings);
    }
}
