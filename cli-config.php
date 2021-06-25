<?php

// Run:
//     vendor/bin/doctrine

require_once("vendor/autoload.php");

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Nimda\Core\Database;

// replace with file to your own project bootstrap
require_once "bootstrap.php";

Database::boot();

// replace with mechanism to retrieve EntityManager in your app
$entityManager = Database::$entityManager;

return ConsoleRunner::createHelperSet($entityManager);
