<?php
// api/journals/update.php

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Only POST allowed']);
    exit;
}

require_once __DIR__ . '/../../database/db.php';
require_once __DIR__ . '/../auth/api_auth_middleware.php';

// AUTH REQUIRED - Admin only
$userId = requireAuth();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

if (!$id) {
    echo json_encode(['ok' => false, 'message' => 'id required']);
    exit;
}

try {
    $updates = [];
    $params = [];

    if (!empty($_POST['title'])) {
        $updates[] = "title = ?";
        $params[] = $_POST['title'];
    }

    if (!empty($_POST['abstract'])) {
        $updates[] = "abstract = ?";
        $params[] = $_POST['abstract'];
    }

    if (!empty($_POST['email'])) {
        $updates[] = "email = ?";
        $params[] = $_POST['email'];
    }

    if (!empty($_POST['contact'])) {
        $updates[] = "contact = ?";
        $params[] = $_POST['contact'];
    }

    if (!empty($_POST['volume'])) {
        $updates[] = "volume = ?";
        $params[] = $_POST['volume'];
    }

    if (isset($_POST['authors'])) {
        $authors = is_string($_POST['authors'])
            ? json_decode($_POST['authors'], true)
            : $_POST['authors'];
        if ($authors && is_array($authors)) {
            $updates[] = "authors = ?";
            $params[] = json_encode($authors);
        }
    }

    if (isset($_POST['tags'])) {
        $tags = is_string($_POST['tags'])
            ? json_decode($_POST['tags'], true)
            : $_POST['tags'];
        if ($tags && is_array($tags)) {
            $updates[] = "tags = ?";
            $params[] = json_encode($tags);
        }
    }

    if (isset($_POST['pengurus'])) {
        $pengurus = is_string($_POST['pengurus'])
            ? json_decode($_POST['pengurus'], true)
            : $_POST['pengurus'];
        if ($pengurus && is_array($pengurus)) {
            $updates[] = "pengurus = ?";
            $params[] = json_encode($pengurus);
        }
    }

    // DYNAMIC upload directory detection
    $upload_dir = __DIR__ . '/../../../uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // ========= FILE PDF UPLOAD =========
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $original_name = basename($_FILES['file']['name']);
        $file_name = uniqid() . '_' . $original_name;
        $file_name = str_replace(' ', '_', $file_name);
        $file_name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $file_name);
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
            $file_url = '/uploads/' . $file_name;

            // Insert to uploads table
            $stmt = $pdo->prepare("INSERT INTO uploads (filename, url, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$file_name, $file_url]);
            $file_upload_id = $pdo->lastInsertId();

            $updates[] = "file_upload_id = ?";
            $params[] = $file_upload_id;

            // Delete old file (physical)
            $old = $pdo->prepare("SELECT file_upload_id FROM journals WHERE id = ?");
            $old->execute([$id]);
            $oldData = $old->fetch(PDO::FETCH_ASSOC);

            if ($oldData && $oldData['file_upload_id']) {
                $oldFile = $pdo->prepare("SELECT filename FROM uploads WHERE id = ?");
                $oldFile->execute([$oldData['file_upload_id']]);
                $oldFileData = $oldFile->fetch(PDO::FETCH_ASSOC);

                if ($oldFileData && !empty($oldFileData['filename'])) {
                    $oldFilePath = $upload_dir . $oldFileData['filename'];
                    if (file_exists($oldFilePath)) {
                        @unlink($oldFilePath);
                    }
                }
            }

            error_log("File uploaded: $file_url (ID: $file_upload_id)");
        }
    }

    // ========= COVER IMAGE UPLOAD =========
    if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
        $original_cover = basename($_FILES['cover']['name']);
        $cover_name = uniqid() . '_' . $original_cover;
        $cover_name = str_replace(' ', '_', $cover_name);
        $cover_name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $cover_name);
        $cover_path = $upload_dir . $cover_name;

        if (move_uploaded_file($_FILES['cover']['tmp_name'], $cover_path)) {
            $cover_url = '/uploads/' . $cover_name;

            $stmt = $pdo->prepare("INSERT INTO uploads (filename, url, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$cover_name, $cover_url]);
            $cover_upload_id = $pdo->lastInsertId();

            $updates[] = "cover_upload_id = ?";
            $params[] = $cover_upload_id;

            // Delete old cover
            $old = $pdo->prepare("SELECT cover_upload_id FROM journals WHERE id = ?");
            $old->execute([$id]);
            $oldData = $old->fetch(PDO::FETCH_ASSOC);

            if ($oldData && $oldData['cover_upload_id']) {
                $oldCover = $pdo->prepare("SELECT filename FROM uploads WHERE id = ?");
                $oldCover->execute([$oldData['cover_upload_id']]);
                $oldCoverData = $oldCover->fetch(PDO::FETCH_ASSOC);

                if ($oldCoverData && !empty($oldCoverData['filename'])) {
                    $oldCoverPath = $upload_dir . $oldCoverData['filename'];
                    if (file_exists($oldCoverPath)) {
                        @unlink($oldCoverPath);
                    }
                }
            }

            error_log("Cover uploaded: $cover_url (ID: $cover_upload_id)");
        }
    }

    // ========= EXECUTE UPDATE =========
    if (!empty($updates)) {
        $updates[] = "updated_at = NOW()";
        $params[] = $id;

        $sql = "UPDATE journals SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'ok' => true,
            'id' => $id,
            'message' => 'Journal updated successfully',
        ]);
    } else {
        echo json_encode(['ok' => false, 'message' => 'No changes detected']);
    }

} catch (PDOException $e) {
    error_log('Update journal error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log('Update journal error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
?>