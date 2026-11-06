<?php

declare(strict_types=1);

namespace App;

use App\Plugin\PluginAutoloader;
use App\Plugin\PluginRegistry;
use App\Security\AppSecretCheck;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private ?PluginRegistry $pluginRegistry = null;

    public function boot(): void
    {
        // Statistics group clicks by local calendar day, so the PHP timezone matters.
        $timezone = $_SERVER['APP_TIMEZONE'] ?? $_ENV['APP_TIMEZONE'] ?? 'Europe/Berlin';
        date_default_timezone_set(\is_string($timezone) && '' !== $timezone ? $timezone : 'Europe/Berlin');

        // Fail-closed: resolves the enabled plugins and throws before the container is built
        // if one of them is misconfigured, then teaches Composer the plugin namespaces. This
        // runs on every boot, so it also runs for a warm (cached) container, where the
        // configureContainer() hooks below do not.
        $this->registerPluginAutoloading();

        parent::boot();

        // Fail fast in production, including for console commands: TOTP seeds are only
        // recoverable with the real APP_SECRET, and a shipped placeholder would make
        // them decryptable for anybody who knows the template.
        if ('prod' === $this->getEnvironment()) {
            AppSecretCheck::assertUsable((string) $this->getContainer()->getParameter('kernel.secret'));
        }
    }

    /**
     * Mirrors the framework's default container configuration (see
     * KernelTrait::configureContainer() in vendor/symfony/dependency-injection) and then
     * imports the services of every enabled plugin.
     */
    protected function configureContainer(ContainerConfigurator $container): void
    {
        $configDir = preg_replace('{/config$}', '/{config}', $this->getConfigDir());

        $container->import($configDir.'/{packages}/*.{php,yaml}');
        $container->import($configDir.'/{packages}/'.$this->environment.'/*.{php,yaml}');

        if (is_file($this->getConfigDir().'/services.yaml')) {
            $container->import($configDir.'/services.yaml');
            $container->import($configDir.'/{services}_'.$this->environment.'.yaml');
        } else {
            $container->import($configDir.'/{services}.php');
            $container->import($configDir.'/{services}_'.$this->environment.'.php');
        }

        foreach ($this->pluginRegistry()->active($this->environment) as $plugin) {
            $services = $plugin->directory.'/services.yaml';
            if (is_file($services)) {
                $container->import($services);
            }
        }
    }

    /**
     * Mirrors the framework's default route configuration (see
     * MicroKernelTrait::configureRoutes() in vendor/symfony/framework-bundle) and then
     * imports the routes of every enabled plugin. A disabled or unlisted plugin is not
     * consulted, so it cannot register a route.
     */
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $configDir = preg_replace('{/config$}', '/{config}', $this->getConfigDir());

        $routes->import($configDir.'/{routes}/'.$this->environment.'/*.{php,yaml}');
        $routes->import($configDir.'/{routes}/*.{php,yaml}');
        if (is_file($this->getConfigDir().'/routes.yaml')) {
            $routes->import($configDir.'/routes.yaml');
        } else {
            $routes->import($configDir.'/{routes}.php');
        }

        foreach ($this->pluginRegistry()->active($this->environment) as $plugin) {
            $routesFile = $plugin->directory.'/config/routes.yaml';
            if (is_file($routesFile)) {
                $routes->import($routesFile);
            }
        }

        if ($fileName = (new \ReflectionObject($this))->getFileName()) {
            $routes->import($fileName, 'attribute');
        }
    }

    /**
     * @return list<string> An array of allowed values for APP_ENV
     */
    private function getAllowedEnvs(): array
    {
        return ['prod', 'dev', 'test'];
    }

    private function registerPluginAutoloading(): void
    {
        $autoloader = new PluginAutoloader();
        foreach ($this->pluginRegistry()->active($this->environment) as $plugin) {
            $autoloader->register($plugin);
        }
    }

    private function pluginRegistry(): PluginRegistry
    {
        return $this->pluginRegistry ??= PluginRegistry::forProject($this->getProjectDir());
    }
}
