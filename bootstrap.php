<?php

// bootstrap.php


use Doctrine\Common\Cache\Psr6\DoctrineProvider;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Psr\Container\ContainerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use App\Services\UserService;
use App\Services\GameService;
use App\Services\CardStateService;
use App\Services\CardService;
use App\Controllers\UserController;
use App\Controllers\HTMLController;
use App\Controllers\GameController;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;
use App\Container;


require_once __DIR__ . '/vendor/autoload.php';

$logger =  new Logger('container');
$logger->pushHandler(new StreamHandler(__DIR__ . '/logs/container.log', Level::Debug));


$container = new Container($logger,require __DIR__ . '/settings.php');

$container->set("view", function() {
    // return Twig::create(__DIR__ . '/app/Views/templates', ['cache' => __DIR__ . '/app/Views/cache']);
    return Twig::create(__DIR__ . '/app/Views/templates', ['cache' => false]);
});

$container->set(LoggerInterface::class, function (ContainerInterface $c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Logger($settings['name']);
    $logger->pushHandler(new StreamHandler($settings['path'], Level::Debug));
    return $logger;
});

$container->set(EntityManager::class, static function (Container $c): EntityManager {
    /** @var array $settings */
    $settings = $c->get('settings');

    // Use the ArrayAdapter or the FilesystemAdapter depending on the value of the 'dev_mode' setting
    // You can substitute the FilesystemAdapter for any other cache you prefer from the symfony/cache library
    $cache = $settings['doctrine']['dev_mode'] ?
        DoctrineProvider::wrap(new ArrayAdapter()) :
        DoctrineProvider::wrap(new FilesystemAdapter(directory: $settings['doctrine']['cache_dir']));

    $config = Setup::createAttributeMetadataConfiguration(
        $settings['doctrine']['metadata_dirs'],
        $settings['doctrine']['dev_mode'],
        null,
        $cache
    );

    return EntityManager::create($settings['doctrine']['connection'], $config);
});

$container->set(UserService::class, static function (Container $c) {
    return new UserService($c->get(EntityManager::class), $c->get(LoggerInterface::class));
});

$container->set(UserController::class, static function (Container $c) {
    return new UserController($c->get(UserService::class));
});

$container->set(HTMLController::class, static function (Container $c) {
    return new HTMLController($c->get("view"), $c->get(GameService::class), $c->get(CardService::class));
});

$container->set(GameService::class, static function (Container $c) {
    return new GameService($c->get(EntityManager::class), $c->get(LoggerInterface::class));
});

$container->set(CardStateService::class, static function (Container $c) {
    return new CardStateService($c->get(EntityManager::class), $c->get(LoggerInterface::class));
});

$container->set(CardService::class, static function (Container $c) {
    return new CardService($c->get(EntityManager::class), $c->get(LoggerInterface::class));
});

$container->set(GameController::class, static function (Container $c) {
    return new GameController($c->get("view"),$c->get(GameService::class), $c->get(CardStateService::class), $c->get(CardService::class));
});


return $container;