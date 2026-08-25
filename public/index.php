<?php
require_once __DIR__ . '/../config/config.php';
SchemaSynchronizer::syncIfNeeded();
$router = new Router();
$router->dispatch($_SERVER['REQUEST_URI']);
