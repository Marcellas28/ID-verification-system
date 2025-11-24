<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $studentId = trim(strtoupper($input['student_id'] ?? ''));
    $photoId = trim($input['photo_id'] ?? '');
    
    if (empty($studentId) || empty($photoId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID and Photo ID are required']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if student exists and get their student number
        $stmt = $pdo->prepare("SELECT student_id, assigned_photo_id FROM students WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            throw new Exception('Student not found');
        }
        
        if (!empty($student['assigned_photo_id'])) {
            throw new Exception('Student already has an assigned photo');
        }
        
        // Check if photo exists and is unassigned
        $stmt = $pdo->prepare("SELECT photo_id, file_path FROM unassigned_photos WHERE photo_id = ? AND assigned = FALSE");
        $stmt->execute([$photoId]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$photo) {
            throw new Exception('Photo not found or already assigned');
        }
        
        // FIX: Sanitize student ID for filename by replacing invalid characters
        $sanitizedStudentId = preg_replace('/[^a-zA-Z0-9_-]/', '_', $studentId);
        $newFileName = $sanitizedStudentId . '.jpg';
        $newFilePath = UPLOAD_ASSIGNED_PATH . $newFileName;
        
        // Alternative: You can also use a hash-based approach
        // $newFileName = md5($studentId) . '.jpg';
        // $newFilePath = UPLOAD_ASSIGNED_PATH . $newFileName;
        
        // Ensure assigned directory exists
        if (!is_dir(UPLOAD_ASSIGNED_PATH)) {
            mkdir(UPLOAD_ASSIGNED_PATH, 0755, true);
        }
        
        // Check if source file exists
        $oldFilePath = BASE_PATH . '/' . $photo['file_path'];
        if (!file_exists($oldFilePath)) {
            throw new Exception('Source photo file not found: ' . $oldFilePath);
        }
        
        // Copy file to assigned folder with new name (sanitized student number)
        if (!copy($oldFilePath, $newFilePath)) {
            throw new Exception('Failed to copy photo file. Check directory permissions.');
        }
        
        // Update database records
        $stmt = $pdo->prepare("UPDATE students SET assigned_photo_id = ?, photo_file_path = ? WHERE student_id = ?");
        $stmt->execute([$photoId, 'uploads/assigned/' . $newFileName, $studentId]);
        
        $stmt = $pdo->prepare("UPDATE unassigned_photos SET assigned = TRUE WHERE photo_id = ?");
        $stmt->execute([$photoId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Photo successfully assigned',
            'assigned_photo_id' => $photoId,
            'new_filename' => $newFileName,
            'student_number' => $studentId,
            'sanitized_filename' => $sanitizedStudentId // For debugging
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Assignment failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>