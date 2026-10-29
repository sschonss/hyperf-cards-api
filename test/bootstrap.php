<?php

declare(strict_types=1);

use Hyperf\Contract\ApplicationInterface;
use Hyperf\Di\ClassLoader;
use Hyperf\Di\ScanHandler\ProcScanHandler;
use Hyperf\Engine\DefaultOption;

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');
error_reporting(E_ALL);
date_default_timezone_set('America/Sao_Paulo');

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require BASE_PATH . '/vendor/autoload.php';

! defined('SWOOLE_HOOK_FLAGS') && define('SWOOLE_HOOK_FLAGS', DefaultOption::hookFlags());

// co-phpunit roda dentro de uma corrotina, e pcntl_fork é proibido aí.
// O scan vai para um processo filho via proc_open.
ClassLoader::init(handler: new ProcScanHandler());

$container = require BASE_PATH . '/config/container.php';
$container->get(ApplicationInterface::class);
