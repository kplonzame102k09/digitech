<?php

require __DIR__.'/../vendor/autoload.php';

// phpunit.xml no longer hard-codes the database name. In environments that can
// create databases (CI, a privileged DB user) leave `DB_TEST_DATABASE` unset and
// the isolated `digitech_portal_test` database is used. Where the app user lacks
// CREATE DATABASE privileges, point it at an existing, disposable database:
//
//   DB_TEST_DATABASE=digitech_db php artisan test
//
// RefreshDatabase rebuilds that schema from migrations, so use a scratch
// database rather than an environment holding real data.
if (getenv('DB_DATABASE') === false || getenv('DB_DATABASE') === '') {
    putenv('DB_DATABASE='.(getenv('DB_TEST_DATABASE') ?: 'digitech_portal_test'));
    $_ENV['DB_DATABASE'] = getenv('DB_DATABASE');
}
