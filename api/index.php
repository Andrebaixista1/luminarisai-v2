<?php

declare(strict_types=1);

// Route all Vercel PHP runtime requests to Laravel's public front controller.
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$queryPos = strpos($uri, '?');
$_SERVER['REQUEST_URI'] = $queryPos === false ? $uri : substr($uri, 0, $queryPos);

require __DIR__.'/../public/index.php';
