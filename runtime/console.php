#!/usr/bin/env php
<?php

use Illuminate\Foundation\Application;
use Symfony\Component\Console\Input\ArgvInput;

define('LARAVEL_START', microtime(true));

require '/app/vendor/autoload.php';

/** @var Application $app */
$app = require_once '/app/bootstrap/app.php';

$status = $app->handleCommand(new ArgvInput);

exit($status);