<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "DEBUG START<br>";
session_start();

require 'vendor/autoload.php';
require 'src/Database.php';
require 'src/Router.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$twig = new \Twig\Environment(
    new \Twig\Loader\FilesystemLoader('templates'),
    ['debug' => true]
);

(new Router($twig))->dispatch();
