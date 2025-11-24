<?php
/**
 * KMTC Student Management Script
 * Usage: http://localhost/kmtc-photo-system/scripts/manage_students.php
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'kmtc_photo_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// File paths
define('BASE_PATH', realpath(dirname(__FILE__) . '/../'));
define('UPLOAD_PENDING_PATH', BASE_PATH . '/uploads/pending/');
define('UPLOAD_ASSIGNED_PATH', BASE_PATH . '/uploads/assigned/');

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function showMessage($message, $type = 'info') {
    $colors = [
        'success' => '#28a745',
        'error' => '#dc3545',
        'warning' => '#ffc107',
        'info' => '#17a2b8'
    ];
    $color = $colors[$type] ?? '#17a2b8';
    echo "<div style='padding: 15px; margin: 10px 0; border-radius: 5px; background: #f8f9fa; border-left: 4px solid $color;'>
            <strong style='color: $color;'>" . strtoupper($type) . ":</strong> $message
          </div>";
}

function resetStudentPhoto($studentId, $pdo) {
    try {
        $pdo->beginTransaction();
        
        // Get student's current photo assignment
        $stmt = $pdo->prepare("SELECT assigned_photo_id, photo_file_path FROM students WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student || empty($student['assigned_photo_id'])) {
            throw new Exception('Student not found or no photo assigned');
        }
        
        $assignedPhotoId = $student['assigned_photo_id'];
        $photoFilePath = $student['photo_file_path'];
        
        // Delete the assigned photo file
        if (!empty($photoFilePath) && file_exists(BASE_PATH . '/' . $photoFilePath)) {
            if (!unlink(BASE_PATH . '/' . $photoFilePath)) {
                throw new Exception('Failed to delete assigned photo file');
            }
        }
        
        // Reset student record
        $stmt = $pdo->prepare("UPDATE students SET assigned_photo_id = NULL, photo_file_path = NULL WHERE student_id = ?");
        $stmt->execute([$studentId]);
        
        // Reset photo record to unassigned
        $stmt = $pdo->prepare("UPDATE unassigned_photos SET assigned = FALSE WHERE photo_id = ?");
        $stmt->execute([$assignedPhotoId]);
        
        $pdo->commit();
        
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteStudent($studentId, $pdo) {
    try {
        $pdo->beginTransaction();
        
        // Get student's current photo assignment
        $stmt = $pdo->prepare("SELECT assigned_photo_id, photo_file_path FROM students WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            throw new Exception('Student not found');
        }
        
        // If student has assigned photo, reset it first
        if (!empty($student['assigned_photo_id'])) {
            $assignedPhotoId = $student['assigned_photo_id'];
            $photoFilePath = $student['photo_file_path'];
            
            // Delete the assigned photo file
            if (!empty($photoFilePath) && file_exists(BASE_PATH . '/' . $photoFilePath)) {
                unlink(BASE_PATH . '/' . $photoFilePath);
            }
            
            // Reset photo record to unassigned
            $stmt = $pdo->prepare("UPDATE unassigned_photos SET assigned = FALSE WHERE photo_id = ?");
            $stmt->execute([$assignedPhotoId]);
        }
        
        // Delete student record
        $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
        $stmt->execute([$studentId]);
        
        $pdo->commit();
        
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Handle actions
if (isset($_GET['action']) && isset($_GET['student_id'])) {
    $studentId = $_GET['student_id'];
    $action = $_GET['action'];
    
    try {
        if ($action === 'reset_photo') {
            if (resetStudentPhoto($studentId, $pdo)) {
                showMessage("Successfully reset photo for student: $studentId. The student can now choose a new photo.", 'success');
            }
        } elseif ($action === 'delete_student') {
            if (deleteStudent($studentId, $pdo)) {
                showMessage("Successfully deleted student: $studentId", 'success');
            }
        }
    } catch (Exception $e) {
        showMessage("Error: " . $e->getMessage(), 'error');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KMTC - Student Management</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #E6F2FF 0%, #ffffff 100%);
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #0056A3, #003366);
            color: white;
            padding: 30px 40px;
        }
        .header h1 {
            margin: 0;
            font-size: 2.2em;
        }
        .content {
            padding: 30px 40px;
        }
        .btn {
            background: linear-gradient(135deg, #0056A3, #003366);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 2px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 86, 163, 0.3);
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        .students-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
        }
        .students-table th {
            background: #0056A3;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .students-table td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .students-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .students-table tr:hover {
            background: #e9ecef;
        }
        .status-assigned {
            color: #28a745;
            font-weight: bold;
        }
        .status-not-assigned {
            color: #6c757d;
        }
        .search-box {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .search-box input {
            padding: 10px;
            width: 300px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-right: 10px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #0056A3;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0056A3;
        }
        .confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>KMTC Student Management</h1>
            <div class="subtitle">Manage students and photo assignments</div>
        </div>
        
        <div class="content">
            <?php
            // Show database statistics
            try {
                $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
                $assignedStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE assigned_photo_id IS NOT NULL")->fetchColumn();
                $unassignedStudents = $totalStudents - $assignedStudents;
                $totalPhotos = $pdo->query("SELECT COUNT(*) FROM unassigned_photos")->fetchColumn();
                $availablePhotos = $pdo->query("SELECT COUNT(*) FROM unassigned_photos WHERE assigned = FALSE")->fetchColumn();
                
                echo "<div class='stats'>";
                echo "<div class='stat-card'><div class='stat-number'>$totalStudents</div><div>Total Students</div></div>";
                echo "<div class='stat-card'><div class='stat-number'>$assignedStudents</div><div>With Photos</div></div>";
                echo "<div class='stat-card'><div class='stat-number'>$unassignedStudents</div><div>Without Photos</div></div>";
                echo "<div class='stat-card'><div class='stat-number'>$availablePhotos/$totalPhotos</div><div>Available Photos</div></div>";
                echo "</div>";
            } catch (PDOException $e) {
                showMessage("Error fetching statistics: " . $e->getMessage(), 'error');
            }
            ?>
            
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search by Student ID, Name, or Registration No..." onkeyup="searchStudents()">
                <button class="btn" onclick="clearSearch()">Clear Search</button>
            </div>
            
            <div id="studentsList">
                <?php
                try {
                    $stmt = $pdo->prepare("
                        SELECT s.student_id, s.full_name, s.registration_no, s.assigned_photo_id, s.photo_file_path, 
                               p.photo_id as photo_identifier
                        FROM students s 
                        LEFT JOIN unassigned_photos p ON s.assigned_photo_id = p.photo_id
                        ORDER BY s.student_id
                    ");
                    $stmt->execute();
                    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($students) > 0) {
                        echo "<h3>All Students (" . count($students) . ")</h3>";
                        echo "<table class='students-table'>";
                        echo "<thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Full Name</th>
                                    <th>Registration No</th>
                                    <th>Photo Status</th>
                                    <th>Assigned Photo</th>
                                    <th>Actions</th>
                                </tr>
                              </thead>";
                        echo "<tbody>";
                        
                        foreach ($students as $student) {
                            $hasPhoto = !empty($student['assigned_photo_id']);
                            $statusClass = $hasPhoto ? 'status-assigned' : 'status-not-assigned';
                            $statusText = $hasPhoto ? '✅ Assigned' : '❌ Not Assigned';
                            $photoInfo = $hasPhoto ? $student['photo_identifier'] . ' → ' . basename($student['photo_file_path']) : '-';
                            
                            echo "<tr class='student-row'>";
                            echo "<td>{$student['student_id']}</td>";
                            echo "<td>{$student['full_name']}</td>";
                            echo "<td>{$student['registration_no']}</td>";
                            echo "<td class='$statusClass'>$statusText</td>";
                            echo "<td>$photoInfo</td>";
                            echo "<td>";
                            
                            if ($hasPhoto) {
                                echo "<a href='?action=reset_photo&student_id={$student['student_id']}' class='btn btn-warning' onclick='return confirm(\"Reset photo for {$student['student_id']}? The photo will return to available pool.\")'>🔄 Reset Photo</a>";
                            }
                            
                            echo "<a href='?action=delete_student&student_id={$student['student_id']}' class='btn btn-danger' onclick='return confirm(\"Permanently delete student {$student['student_id']}? This cannot be undone.\")'>🗑️ Delete</a>";
                            echo "</td>";
                            echo "</tr>";
                        }
                        
                        echo "</tbody>";
                        echo "</table>";
                    } else {
                        echo "<div style='text-align: center; padding: 40px; color: #6c757d;'>";
                        echo "<h3>No Students Found</h3>";
                        echo "<p>No students have been imported yet.</p>";
                        echo "<a href='import_excel.php' class='btn btn-success'>📊 Import Students</a>";
                        echo "</div>";
                    }
                    
                } catch (PDOException $e) {
                    showMessage("Error fetching students: " . $e->getMessage(), 'error');
                }
                ?>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="../" class="btn">🏠 Back to Main System</a>
                <a href="import_excel.php" class="btn btn-success">📊 Import Students</a>
                <a href="import_photos.php" class="btn btn-success">📷 Import Photos</a>
            </div>
        </div>
    </div>

    <script>
        function searchStudents() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const rows = document.querySelectorAll('.student-row');
            
            rows.forEach(row => {
                const text = row.textContent.toUpperCase();
                if (text.indexOf(filter) > -1) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            searchStudents();
        }
        
        // Confirm before destructive actions
        function confirmAction(message) {
            return confirm(message);
        }
    </script>
</body>
</html>