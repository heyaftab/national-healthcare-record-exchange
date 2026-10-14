<?php
declare(strict_types=1);

// Dispatch each requested PHP page through Vercel's PHP runtime while keeping
// the project's original page files and relative includes in the repository.
$requestedPath = (string) ($_GET['path'] ?? '');
$requestedPath = parse_url('/' . ltrim($requestedPath, '/'), PHP_URL_PATH) ?: '/';
$requestedPath = rawurldecode($requestedPath);
$originalPath = $requestedPath;
$queryParameters = $_GET;
unset($queryParameters['path']);
$originalQuery = http_build_query($queryParameters);
$_GET = $queryParameters;
$_SERVER['QUERY_STRING'] = $originalQuery;

if ($requestedPath === '/' || $requestedPath === '') {
    $requestedPath = '/index.php';
}
if ($requestedPath === '/api/index.php') {
    $requestedPath = '/index.php';
}

$projectRoot = dirname(__DIR__);
$target = realpath($projectRoot . $requestedPath);
$rootPrefix = $projectRoot . DIRECTORY_SEPARATOR;

if ($target === false || !str_starts_with($target, $rootPrefix) || !is_file($target)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not Found';
    exit;
}

$extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
if ($extension !== 'php') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Not Found';
    exit;
}

// Restore the original URL path for application logic and redirects.
$_SERVER['SCRIPT_NAME'] = $requestedPath;
$_SERVER['PHP_SELF'] = $requestedPath;
$_SERVER['REQUEST_URI'] = $originalPath . ($originalQuery !== '' ? '?' . $originalQuery : '');
chdir($projectRoot);
require $target;
