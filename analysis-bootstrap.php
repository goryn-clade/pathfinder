<?php
/**
 * Rector bootstrap — stops F3 killing analysis workers on PHP 8.5 deprecations.
 *
 * F3's constructor installs an error handler that turns every E_DEPRECATED into
 * a fatal "ERROR 500". Rector and PHPStan's own vendor code still trigger 8.5
 * deprecations, so any file that touches \Base::instance() kills the worker.
 * Construct the F3 singleton up front, then reinstall a handler that ignores
 * deprecations (the constructor never runs again). Also drop F3's autoloader:
 * it is a protected method, so PHPStan's reflection calling it from outside
 * fails with "Invalid method autoload". Unregister it from inside the object.
 */
require_once __DIR__ . '/vendor/autoload.php';
(function (): void {
    spl_autoload_unregister([$this, 'autoload']);
})->call(\Base::instance());
set_error_handler(static fn (int $errno): bool => $errno === E_DEPRECATED);
