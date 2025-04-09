<?php
require __DIR__ . '/vendor/autoload.php';
use ChiwiCrypt\Controller\ApiController;
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env');
$dotenv->load();
$controller = new ApiController();
$controller->handleRequest();