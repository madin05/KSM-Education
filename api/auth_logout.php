<?php
// auth_logout.php
session_start();
session_destroy();
echo json_encode(['ok'=>true]);
