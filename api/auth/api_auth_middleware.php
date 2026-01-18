<?php
// api/auth/middleware.php
// Helper function untuk check authentication di endpoint lain

// function requireAuth() {
//     if (session_status() === PHP_SESSION_NONE) {
//         session_start();
//     }

//     if (!isset($_SESSION['user_id'])) {
//         http_response_code(401);
//         echo json_encode(['ok' => false, 'message' => 'Authentication required']);
//         exit;
//     }

//     return $_SESSION['user_id'];
// }

// function requireRole($role) {
//     $userId = requireAuth();

//     if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $role) {
//         http_response_code(403);
//         echo json_encode(['ok' => false, 'message' => 'Forbidden']);
//         exit;
//     }

//     return $userId;
// }

// function getCurrentUserId() {
//     if (session_status() === PHP_SESSION_NONE) {
//         session_start();
//     }

//     return $_SESSION['user_id'] ?? null;
// }
?>