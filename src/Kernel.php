<?php

declare(strict_types=1);

namespace App;

use App\Plugin\PluginAutoloader;
use App\Plugin\PluginMetadata;
use App\Plugin\PluginRegistry;
use App\Security\AppSecretCheck;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private ?PluginRegistry $pluginRegistry = null;

    /** @var list<PluginMetadata>|null */
    private ?array $activePlugins = null;

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

        foreach ($this->activePlugins() as $plugin) {
            // The operator's settings for a plugin are free-form and only known at build
            // time, so they become a container parameter the plugin can inject from its own
            // services.yaml as '%app.plugins.<name>.settings%'. Set before importing the
            // plugin's services, which may reference it. The parameter name is the contract
            // (documented in plugins/README.md) - a plugin must be able to rely on it.
            $container->parameters()->set('app.plugins.'.$plugin->name.'.settings', $plugin->settings);

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

        foreach ($this->activePlugins() as $plugin) {
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
     * The compiled container belongs to exactly one set of enabled plugins.
     *
     * Without this, enabling, disabling or reconfiguring a plugin in a production install
     * (no cache freshness checks) kept serving the container compiled for the previous set
     * until somebody ran cache:clear - and a disabled plugin was worse than ignored: its
     * listeners were still wired while its classes were no longer autoloaded, so every
     * redirect failed with "Class not found". The fingerprint of the active set becomes part
     * of the container class name, so a changed set simply finds no compiled container and
     * builds its own on the first request. With no plugin enabled the name is the framework
     * default. Old containers are removed with the build directory by the next cache:clear.
     */
    protected function getContainerClass(): string
    {
        $fingerprint = $this->pluginFingerprint();

        return parent::getContainerClass().('' === $fingerprint ? '' : 'P'.$fingerprint);
    }

    /**
     * The router cache (url_matching_routes.php, ...) lives in the build directory under a
     * fixed name and is not checked for freshness in production either, so it follows the
     * plugin fingerprint just like the container: plugin routes appear and disappear together
     * with the plugin's services.
     */
    protected function build(ContainerBuilder $container): void
    {
        $fingerprint = $this->pluginFingerprint();
        if ('' === $fingerprint) {
            return;
        }

        $container->addCompilerPass(new class($fingerprint) implements CompilerPassInterface {
            public function __construct(private readonly string $fingerprint)
            {
            }

            public function process(ContainerBuilder $container): void
            {
                $container->setParameter('router.cache_dir', '%kernel.build_dir%/routes-'.$this->fingerprint);
            }
        });
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
        foreach ($this->activePlugins() as $plugin) {
            $autoloader->register($plugin);
        }
    }

    /**
     * The enabled plugins, resolved once per kernel and memoised across requests in the cache
     * directory (see PluginRegistry::activeCached()).
     *
     * @return list<PluginMetadata>
     */
    private function activePlugins(): array
    {
        return $this->activePlugins ??= $this->pluginRegistry()->activeCached($this->environment, $this->getCacheDir().'/plugins.cache');
    }

    /**
     * Short hash over everything a plugin contributes to the compiled container: name,
     * version, settings and location of every enabled plugin. Empty when none is enabled.
     */
    private function pluginFingerprint(): string
    {
        $plugins = $this->activePlugins();
        if ([] === $plugins) {
            return '';
        }

        return substr(hash('xxh128', serialize(array_map(
            static fn (PluginMetadata $plugin): array => [$plugin->name, $plugin->version, $plugin->requiresApi, $plugin->settings, $plugin->directory],
            $plugins,
        ))), 0, 12);
    }

    private function pluginRegistry(): PluginRegistry
    {
        return $this->pluginRegistry ??= PluginRegistry::forProject($this->getProjectDir());
    }
}
