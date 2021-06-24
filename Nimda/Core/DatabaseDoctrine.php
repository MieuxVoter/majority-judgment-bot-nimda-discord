<?php declare(strict_types=1);

namespace Nimda\Core;

use Doctrine\ORM\ORMException;
use Nimda\Configuration\Database as Config;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;


class DatabaseDoctrine
{
    public static EntityManager $entityManager;

    /**
     * @throws ORMException
     */
    public static function boot(): void
    {
        // Create a simple "default" Doctrine ORM configuration for Annotations
        $isDevMode = true;
        $proxyDir = null;
        $cache = null;
        $useSimpleAnnotationReader = false;
        $config = Setup::createAnnotationMetadataConfiguration(
            array(NIMDA_PATH),
            $isDevMode,
            $proxyDir,
            $cache,
            $useSimpleAnnotationReader
        );

        // database configuration parameters
        $conn = array(
            'driver' => 'pdo_sqlite',
            'path' => VAR_PATH . 'database.sqlite',
        );

        self::$entityManager = EntityManager::create($conn, $config);
    }

    public static function repo(string $class) {
        return self::$entityManager->getRepository($class);
    }
}