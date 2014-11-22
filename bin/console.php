<?php
use Symfony\Component\Console\Application;
use Console\Helper;

$basePath = realpath(__DIR__ . '/..');
$autoload = $basePath . "/vendor/autoload.php";
$commandDir = $basePath . '/src/Command';

if (!file_exists($autoload)) {
    die(<<<MSG
Please run "composer install" to install dependencies and create autoload file.

MSG
    );
}

require $autoload;

set_time_limit(0);

$console = new Application('Agendador Seguro Desemprego', '0.0.1-dev');

// recursively add 'Command.php' files in Command directory
$recursive = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($commandDir));

foreach ($recursive as $iterator) {
    if ($iterator->isDir()) {
        continue;
    }

    $filePath = $iterator->getFileInfo()->getPathname();

    if (strpos($filePath, 'Command.php') !== false) {
        $className = Helper::findNamespace($filePath) . '\\' . $iterator->getFileName();
        $className = str_replace('.php', '', $className);
        $console->add(new $className);
    }
}

//$console->setDefaultCommand('run');

$console->run();