<?php
require_once 'config.php';

// Parse request URI
$requestUri = str_replace('/api/', '', $_SERVER['REQUEST_URI']);
$requestUri = trim($requestUri, '/');
$segments = explode('/', $requestUri);
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$subResource = $segments[2] ?? null;

$method = $_SERVER['REQUEST_METHOD'];

// Public endpoints (no authentication required)
$publicEndpoints = ['auth/login', 'auth/register'];

$isPublic = in_array($resource . '/' . ($segments[1] ?? ''), $publicEndpoints) || $resource === 'auth';

if (!$isPublic) {
    validateApiKey();
}

// Route to appropriate handler
switch ($resource) {
    case 'auth':
        require_once 'auth.php';
        handleAuth($method, $segments[1] ?? '');
        break;
    case 'houses':
        require_once 'houses.php';
        handleHouses($method, $id);
        break;
    case 'categories':
        require_once 'categories.php';
        handleCategories($method, $id);
        break;
    case 'tenants':
        require_once 'tenants.php';
        handleTenants($method, $id);
        break;
    case 'payments':
        require_once 'payments.php';
        handlePayments($method, $id, $subResource);
        break;
    case 'reports':
        require_once 'reports.php';
        handleReports($method, $subResource);
        break;
    case 'users':
        require_once 'users.php';
        handleUsers($method, $id);
        break;
    case 'dashboard':
        require_once 'dashboard.php';
        handleDashboard();
        break;
    default:
        sendResponse(['error' => 'Endpoint not found'], 404);
}