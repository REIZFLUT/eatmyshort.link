<?php

declare(strict_types=1);

namespace App\Plugin;

use Composer\Autoload\ClassLoader;

/**
 * Makes a plugin's classes resolvable without a "composer dump-autoload" after the plugin
 * is added or removed.
 *
 * The app's Composer class loader is looked up through Composer's own registry
 * (ClassLoader::getRegisteredLoaders()). Scanning spl_autoload_functions() directly is not
 * enough: in debug mode Symfony's DebugClassLoader wraps every registered autoloader, so
 * the Composer instance is no longer among the top-level autoload functions even though it
 * still does the loading.
 *
 * The plugin's PSR-4 namespace (derived from the directory name) is pointed at its src/
 * directory. Registration is idempotent: the same prefix/path pair is only added once.
 *
 * @internal called by the kernel on every boot, before the container is compiled and again
 *           on a warm container so plugin classes resolve at runtime too
 */
final class PluginAutoloader
{
    public function register(PluginMetadata $plugin): void
    {
        $loader = $this->composerLoader();
        $prefix = PluginRegistry::namespaceFor($plugin->name).'\\';
        $path = $plugin->directory.'/src';

        foreach ($loader->getPrefixesPsr4()[$prefix] ?? [] as $registered) {
            if ($registered === $path) {
                return;
            }
        }

        $loader->addPsr4($prefix, $path);
    }

    private function composerLoader(): ClassLoader
    {
        $loader = array_values(ClassLoader::getRegisteredLoaders())[0] ?? null;
        if ($loader instanceof ClassLoader) {
            return $loader;
        }

        // Fallback for a loader that Composer did not register through its own registry.
        foreach (spl_autoload_functions() as $function) {
            if (\is_array($function) && $function[0] instanceof ClassLoader) {
                return $function[0];
            }
        }

        throw new PluginException('Cannot register the plugin namespaces: no Composer class loader is active.');
    }
}
