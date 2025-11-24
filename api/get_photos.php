<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get pagination parameters
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 12;
        $offset = ($page - 1) * $limit;
        
        // Get total count of unassigned photos
        $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM unassigned_photos WHERE assigned = FALSE");
        $countStmt->execute();
        $totalPhotos = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($totalPhotos / $limit);
        
        // Get photos for current page
        $stmt = $pdo->prepare("SELECT photo_id, file_path FROM unassigned_photos WHERE assigned = FALSE ORDER BY photo_id LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // DEBUG: Check what we have
        $debug_info = [
            'base_path' => BASE_PATH,
            'upload_pending_path' => UPLOAD_PENDING_PATH,
            'photos_found' => count($photos),
            'server_document_root' => $_SERVER['DOCUMENT_ROOT'],
            'current_script_path' => __FILE__
        ];
        
        // Process each photo
        foreach ($photos as &$photo) {
            // Extract just the filename from the stored path
            $filename = basename($photo['file_path']);
            
            // Try different path options to find what works
            $possible_paths = [
                'option1' => 'uploads/pending/' . $filename,
                'option2' => '../uploads/pending/' . $filename,
                'option3' => '/kmtc-photo-system/uploads/pending/' . $filename,
                'option4' => './uploads/pending/' . $filename
            ];
            
            // Check which path actually exists
            $selected_path = $possible_paths['option1']; // default
            foreach ($possible_paths as $option => $path) {
                $full_path = BASE_PATH . '/' . ltrim($path, './');
                if (file_exists($full_path)) {
                    $selected_path = $path;
                    $photo['debug_file_exists'] = true;
                    $photo['debug_selected_path'] = $selected_path;
                    break;
                }
            }
            
            $photo['file_path'] = $selected_path;
            $photo['debug_filename'] = $filename;
            $photo['debug_stored_path'] = $photo['file_path']; // original from DB
        }
        
        echo json_encode([
            'success' => true,
            'photos' => $photos,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_photos' => $totalPhotos,
            'photos_per_page' => $limit,
            'debug' => $debug_info
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>