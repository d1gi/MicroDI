<?php
namespace MicroDI;

use MicroDI\Core\Container;
use Symfony\Component\ClassLoader\UniversalClassLoader;

include(__DIR__ .  '/vendors/Symfony/Component/ClassLoader/UniversalClassLoader.php');

$loader = new UniversalClassLoader;
$loader->registerNamespaces(array(
    'MicroDI' => __DIR__ . '/src',
    'Symfony' => __DIR__ . '/vendors',
));

$loader->registerNamespaceFallbacks(array(
    __DIR__.'/src',
));
$loader->register();


$config = array(
    'test' => array(
        'class' => 'MicroDI\Test\TestService',
        'properties' => array(
            'altTestService' => '@altTest',
            'adapters'   => array('@firstAdapter', '@secondAdapter'),
            'db' => '@pdo',
        ),
        'construct' => array(
            'someAdapter' => '@thirdAdapter',
            'simpleValue' => 123,
        ),
    ),
    'altTest' => array(
        'class' => 'MicroDI\Test\AltTestService',
        'properties' => array(
            //'test' => '@test', // throw recursive dependencies
            'value' => array(1, 2, 3),
        ),
    ),
    'firstAdapter' => array(
        'class' => 'MicroDI\Test\Adapter\FirstAdapter',
    ),
    'secondAdapter' => array(
        'class' => 'MicroDI\Test\Adapter\SecondAdapter',
    ),
    'thirdAdapter' => array(
        'class' => 'MicroDI\Test\Adapter\SecondAdapter',
        'methods' => array(
            'setState' => array('state' => true,)
        ),
    ),
    'pdo' => array(
        'class' => 'PDO',
        'construct' => array(
            'dsn'      => 'mysql:dbname=fgdev;host=127.0.0.1',
            'username' => 'root',
            'passwd'   => 'root',
        ),
    ),
);

$container   = new Container($config);
$testService = $container->get('test');

var_export($testService);
