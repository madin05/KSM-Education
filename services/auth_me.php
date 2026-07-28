<?php
/**
 * GET /services/auth_me.php
 * 
 * Returns the currently authenticated user.
 * Supports JWT (primary) and PHP Session (fallback).
 */

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/jwt_middleware.php';
require_once __DIR__ . '/phase3_helpers.php';

$auth_user = require_auth();

// Fetch fresh user data from DB
$user = phase3_active_user($pdo, (int)$auth_user['id']);

if (!$user) {
    phase3_respond(['ok'=>false,'message'=>'User not found or account is not active.'], 401);
}

phase3_respond([
    'ok'=>true,
    'user'=>phase3_public_user($user),
    'auth_method' => $auth_user['auth_method']
]);
