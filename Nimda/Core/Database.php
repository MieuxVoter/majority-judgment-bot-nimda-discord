<?php declare(strict_types=1);

namespace Nimda\Core;

use Doctrine\ORM\ORMException;
use Nimda\Configuration\Database as Config;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;


class Database
{
    public static EntityManager $entityManager;

    /**
     * @throws ORMException
     */
    public static function boot(): void
    {
        // Create a simple "default" Doctrine ORM configuration for Annotations
        $isDevMode = true;  // → Caching is done in RAM (ArrayCache)
        $proxyDir = null;  // Use system tmp
        $cache = null;    // Hopefully overruled by isdevMode
        $useSimpleAnnotationReader = false;
        $config = Setup::createAnnotationMetadataConfiguration(
            array(NIMDA_PATH),
            $isDevMode,
            $proxyDir,
            $cache,
            $useSimpleAnnotationReader
        );

        $conn = array(
            'driver' => 'pdo_'.getenv('DATABASE_DRIVER'),
        );

        self::addFromEnv($conn, 'path', 'DATABASE_PATH');
        self::addFromEnv($conn, 'charset', 'DATABASE_CHARSET');
        self::addFromEnv($conn, 'user', 'DATABASE_USER');
        self::addFromEnv($conn, 'password', 'DATABASE_PASS');

        self::$entityManager = EntityManager::create($conn, $config);
    }

    public static function repo(string $class) {
        return self::$entityManager->getRepository($class);
    }

    public static function addFromEnv(array &$conn, string $connKey, string $envKey) {
        $value = getenv($envKey);
        if (false !== $value) {
            $conn[$connKey] = $value;
        }
    }
}