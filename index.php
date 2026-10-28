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

// generic "/api/@controller/@action" routes: F3 url-decodes the path and @controller matches anything but "/".
// Allow only plain names of existing Api controllers (e.g. no "Rest%5CSignature"). "/api/rest/..." has its own routes
if(
    preg_match('#^/api/([^/]*)/[^/]#', urldecode((string)$f3->get('PATH')), $matches) &&
    $matches[1] !== 'rest' &&
    (
        !preg_match('/^[A-Za-z]+$/', $matches[1]) ||
        !class_exists(__NAMESPACE__ . '\Controller\Api\\' . $matches[1])
    )
){
    $f3->error(404);
}else{
    $f3->run();
}