<?php

// Run:
//     vendor/bin/doctrine

require_once("vendor/autoload.php");

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Nimda\Core\DatabaseDoctrine;

// replace with file to your own project bootstrap
require_once "bootstrap.php";

DatabaseDoctrine::boot();

// replace with mechanism to retrieve EntityManager in your app
$entityManager = DatabaseDoctrine::$entityManager;

return ConsoleRunner::createHelperSet($entityManager);
