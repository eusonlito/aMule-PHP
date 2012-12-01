<?php
namespace Lito\Amule;

header('Content-Type: text/html; charset=utf-8');

set_time_limit(0);

mb_internal_encoding('UTF-8');

define('DOCUMENT_ROOT', preg_replace('#[/\\\]+#', '/', realpath(getenv('DOCUMENT_ROOT'))));
define('BASE_PATH', preg_replace('#[/\\\]+#', '/', realpath(__DIR__.'/../../../').'/'));

require (__DIR__.'/functions.php');
require (__DIR__.'/Autoload.php');

Autoload::register();

require (BASE_PATH.'/settings.php');

$Api = new Amule($settings);
