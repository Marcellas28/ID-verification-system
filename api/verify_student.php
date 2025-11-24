<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $studentId = trim(strtoupper($input['student_id'] ?? ''));
    
    if (empty($studentId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Student ID is required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT student_id, full_name, registration_no, assigned_photo_id FROM students WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            echo json_encode([
                'success' => true,
                'student' => [
                    'id' => $student['student_id'],
                    'name' => $student['full_name'],
                    'regNo' => $student['registration_no'],
                    'hasAssignedPhoto' => !empty($student['assigned_photo_id'])
                ]
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Student ID not found']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>