<?php
namespace Exodus4D\Pathfinder;

use Exodus4D\Pathfinder\Lib;

session_name('pathfinder_session');

$composerAutoloader = 'vendor/autoload.php';
if(file_exists($composerAutoloader)){
    require_once($composerAutoloader);
}else{
    die("Couldn't find '$composerAutoloader'. Did you run `composer install`?");
}

$f3 = \Base::instance();
$f3->set('NAMESPACE', __NAMESPACE__);

// load main config
$f3->config('app/config.ini', true);

// load environment dependent config
Lib\Config::instance($f3);

// initiate cron-jobs
Lib\Cron::instance();

// "/api/@controller/@action" and "/api/rest/@controller*": F3 url-decodes the path and @controller matches anything but "/".
// Allow only plain names of Api (or Api\Rest) controllers that exist and can be instantiated
// (no "Rest%5CSignature", no abstract classes). Everything else is a 404 before F3 runs
$controllerClass = null;
if(preg_match('#^/api/rest/([^/]*)#i', $path = urldecode((string)$f3->get('PATH')), $matches)){
    $controllerClass = __NAMESPACE__ . '\\Controller\\Api\\Rest\\' . $matches[1];
}elseif(preg_match('#^/api/([^/]*)/[^/]#', $path, $matches)){
    $controllerClass = __NAMESPACE__ . '\\Controller\\Api\\' . $matches[1];
}

if(
    $controllerClass &&
    (
        !preg_match('/^[A-Za-z]+$/', $matches[1]) ||
        !class_exists($controllerClass) ||
        !(new \ReflectionClass($controllerClass))->isInstantiable()
    )
){
    $f3->error(404);
}else{
    $f3->run();
}
