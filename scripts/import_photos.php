<?php
/**
 * KMTC Photo Import Script
 * Usage: http://localhost/kmtc-photo-system/scripts/import_photos.php
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

// Function to delete a single photo
function deletePhoto($photoId, $pdo) {
    try {
        // Get photo details
        $stmt = $pdo->prepare("SELECT file_path FROM unassigned_photos WHERE photo_id = ?");
        $stmt->execute([$photoId]);
        $photo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$photo) {
            throw new Exception('Photo not found in database');
        }
        
        // Delete physical file
        $filePath = BASE_PATH . '/' . $photo['file_path'];
        if (file_exists($filePath)) {
            if (!unlink($filePath)) {
                throw new Exception('Failed to delete physical file');
            }
        }
        
        // Delete from database
        $stmt = $pdo->prepare("DELETE FROM unassigned_photos WHERE photo_id = ?");
        $stmt->execute([$photoId]);
        
        return true;
    } catch (Exception $e) {
        throw new Exception('Delete failed: ' . $e->getMessage());
    }
}

// Function to delete all photos
function deleteAllPhotos($pdo) {
    try {
        // Get all photo file paths
        $stmt = $pdo->prepare("SELECT file_path FROM unassigned_photos");
        $stmt->execute();
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $deletedFiles = 0;
        $errors = 0;
        
        // Delete physical files
        foreach ($photos as $photo) {
            $filePath = BASE_PATH . '/' . $photo['file_path'];
            if (file_exists($filePath)) {
                if (unlink($filePath)) {
                    $deletedFiles++;
                } else {
                    $errors++;
                }
            }
        }
        
        // Delete all from database
        $stmt = $pdo->prepare("DELETE FROM unassigned_photos");
        $stmt->execute();
        $deletedRecords = $stmt->rowCount();
        
        return [
            'deleted_files' => $deletedFiles,
            'deleted_records' => $deletedRecords,
            'errors' => $errors
        ];
    } catch (Exception $e) {
        throw new Exception('Delete all failed: ' . $e->getMessage());
    }
}

function importPhotosFromFolder($folderPath, $pdo) {
    if (!is_dir($folderPath)) {
        showMessage("Folder not found: $folderPath", 'error');
        return;
    }
    
    $files = scandir($folderPath);
    $imported = 0;
    $errors = 0;
    $skipped = 0;
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $folderPath . $file;
        $fileInfo = pathinfo($filePath);
        
        // Only process image files
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        $fileExtension = strtolower($fileInfo['extension'] ?? '');
        
        if (in_array($fileExtension, $imageExtensions)) {
            // Generate photo ID from filename (without extension)
            $photoId = strtoupper($fileInfo['filename']);
            
            try {
                // Check if photo already exists
                $checkStmt = $pdo->prepare("SELECT id FROM unassigned_photos WHERE photo_id = ?");
                $checkStmt->execute([$photoId]);
                
                if (!$checkStmt->fetch()) {
                    // Get file info
                    $fileSize = file_exists($filePath) ? filesize($filePath) : 0;
                    $fileType = mime_content_type($filePath);
                    
                    // Insert new photo record
                    $stmt = $pdo->prepare("INSERT INTO unassigned_photos (photo_id, file_path, file_size, file_type) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $photoId,
                        'uploads/pending/' . $file,
                        $fileSize,
                        $fileType
                    ]);
                    $imported++;
                } else {
                    $skipped++;
                }
            } catch (PDOException $e) {
                $errors++;
            }
        }
    }
    
    showMessage("Successfully imported: $imported photos", 'success');
    if ($skipped > 0) {
        showMessage("Skipped (already exists): $skipped photos", 'warning');
    }
    if ($errors > 0) {
        showMessage("Errors: $errors photos", 'error');
    }
    
    return $errors === 0;
}

function handlePhotoUpload($uploadedFiles, $pdo) {
    $uploadedCount = 0;
    $errorCount = 0;
    
    // Ensure upload directory exists
    if (!is_dir(UPLOAD_PENDING_PATH)) {
        mkdir(UPLOAD_PENDING_PATH, 0755, true);
    }
    
    foreach ($uploadedFiles['tmp_name'] as $key => $tmpName) {
        if ($uploadedFiles['error'][$key] !== UPLOAD_ERR_OK) {
            $errorCount++;
            continue;
        }
        
        $originalName = $uploadedFiles['name'][$key];
        $fileInfo = pathinfo($originalName);
        $fileExtension = strtolower($fileInfo['extension'] ?? '');
        
        // Check if it's an image
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileExtension, $imageExtensions)) {
            $errorCount++;
            continue;
        }
        
        // Generate unique filename if needed, or use original
        $newFilename = $fileInfo['filename'] . '.' . $fileExtension;
        $destination = UPLOAD_PENDING_PATH . $newFilename;
        
        // If file already exists, add a number
        $counter = 1;
        while (file_exists($destination)) {
            $newFilename = $fileInfo['filename'] . '_' . $counter . '.' . $fileExtension;
            $destination = UPLOAD_PENDING_PATH . $newFilename;
            $counter++;
        }
        
        if (move_uploaded_file($tmpName, $destination)) {
            // Import to database
            $photoId = strtoupper($fileInfo['filename']);
            
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM unassigned_photos WHERE photo_id = ?");
                $checkStmt->execute([$photoId]);
                
                if (!$checkStmt->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO unassigned_photos (photo_id, file_path, file_size, file_type) VALUES (?, ?, ?, ?)");
                    $stmt->execute([
                        $photoId,
                        'uploads/pending/' . $newFilename,
                        $uploadedFiles['size'][$key],
                        $uploadedFiles['type'][$key]
                    ]);
                    $uploadedCount++;
                }
            } catch (PDOException $e) {
                $errorCount++;
            }
        } else {
            $errorCount++;
        }
    }
    
    showMessage("Successfully uploaded: $uploadedCount photos", 'success');
    if ($errorCount > 0) {
        showMessage("Errors: $errorCount files", 'error');
    }
    
    return $errorCount === 0;
}

// Handle delete actions
if (isset($_GET['delete_photo'])) {
    $photoId = $_GET['delete_photo'];
    try {
        if (deletePhoto($photoId, $pdo)) {
            showMessage("Photo $photoId deleted successfully", 'success');
        }
    } catch (Exception $e) {
        showMessage($e->getMessage(), 'error');
    }
}

if (isset($_GET['delete_all'])) {
    try {
        $result = deleteAllPhotos($pdo);
        showMessage("Deleted {$result['deleted_records']} photos from database and {$result['deleted_files']} files from server", 'success');
        if ($result['errors'] > 0) {
            showMessage("Failed to delete {$result['errors']} files", 'warning');
        }
    } catch (Exception $e) {
        showMessage($e->getMessage(), 'error');
    }
}

// Handle photo uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photos'])) {
    handlePhotoUpload($_FILES['photos'], $pdo);
}

// Handle folder import
if (isset($_GET['import'])) {
    echo "<h2>Starting Photo Import from Folder...</h2>";
    importPhotosFromFolder(UPLOAD_PENDING_PATH, $pdo);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KMTC - Photo Import</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #E6F2FF 0%, #ffffff 100%);
            margin: 0;
            padding: 15px;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #0056A3, #003366);
            color: white;
            padding: 25px 20px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 1.8em;
        }

        .header .subtitle {
            opacity: 0.9;
            margin-top: 5px;
        }

        .content {
            padding: 25px 20px;
        }

        .btn {
            background: linear-gradient(135deg, #0056A3, #003366);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 5px;
            text-align: center;
            min-height: 44px;
            min-width: 44px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 86, 163, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #e0a800);
        }

        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
        }

        .instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .upload-section {
            background: #e7f3ff;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            border: 2px dashed #0056A3;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #0056A3;
        }

        .form-group input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px dashed #0056A3;
            border-radius: 8px;
            background: #f8f9fa;
            font-size: 16px;
        }

        .tab-container {
            margin: 20px 0;
        }

        .tab-buttons {
            display: flex;
            margin-bottom: 15px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #ddd;
            flex-wrap: wrap;
        }

        .tab-button {
            padding: 12px 15px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            flex: 1;
            transition: all 0.3s ease;
            min-width: 120px;
        }

        .tab-button.active {
            background: #0056A3;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .photos-list {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            max-height: 400px;
            overflow-y: auto;
        }

        .photo-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            background: white;
            margin: 5px 0;
            border-radius: 5px;
            border-left: 4px solid #0056A3;
            flex-wrap: wrap;
            gap: 10px;
        }

        .photo-info {
            flex: 1;
            min-width: 200px;
        }

        .photo-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .danger-zone {
            background: #ffe6e6;
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin: 15px 0;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #0056A3;
        }

        .stat-number {
            font-size: 1.8em;
            font-weight: bold;
            color: #0056A3;
        }

        .status-assigned {
            color: #28a745;
            font-weight: 600;
            font-size: 0.9em;
        }

        .status-available {
            color: #6c757d;
            font-weight: 600;
            font-size: 0.9em;
        }

        /* Mobile Styles */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .container {
                border-radius: 10px;
            }

            .header {
                padding: 20px 15px;
            }

            .header h1 {
                font-size: 1.5em;
            }

            .content {
                padding: 20px 15px;
            }

            .tab-buttons {
                flex-direction: column;
            }

            .tab-button {
                min-width: auto;
                text-align: center;
            }

            .photo-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .photo-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .btn {
                padding: 10px 15px;
                font-size: 13px;
                margin: 3px;
                width: 100%;
                max-width: 200px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .form-group input[type="file"] {
                padding: 10px;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.3em;
            }

            .btn {
                font-size: 12px;
                padding: 8px 12px;
            }

            .photo-info {
                min-width: auto;
            }

            .photo-actions {
                justify-content: center;
            }

            .photo-actions .btn {
                max-width: none;
            }
        }

        /* Prevent horizontal scroll */
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>KMTC Photo Import</h1>
            <div class="subtitle">Import passport photos into the database</div>
        </div>
        
        <div class="content">
            <?php
            // Show database stats
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total, COUNT(CASE WHEN assigned = TRUE THEN 1 END) as assigned FROM unassigned_photos");
                $stmt->execute();
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo "<div class='stats-grid'>";
                echo "<div class='stat-card'><div class='stat-number'>{$stats['total']}</div><div>Total Photos</div></div>";
                echo "<div class='stat-card'><div class='stat-number'>{$stats['assigned']}</div><div>Assigned Photos</div></div>";
                echo "<div class='stat-card'><div class='stat-number'>" . ($stats['total'] - $stats['assigned']) . "</div><div>Available Photos</div></div>";
                echo "</div>";
            } catch (PDOException $e) {
                showMessage("Database error: " . $e->getMessage(), 'error');
            }
            ?>
            
            <div class="tab-container">
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="openTab('upload')">📤 Upload Photos</button>
                    <button class="tab-button" onclick="openTab('import')">📁 Import from Folder</button>
                    <button class="tab-button" onclick="openTab('manage')">📋 Manage Photos</button>
                </div>
                
                <div id="upload" class="tab-content active">
                    <div class="upload-section">
                        <h3>📤 Upload Photos Directly</h3>
                        <p>Select photos from your computer to upload directly to the system.</p>
                        
                        <form method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="photos">Select Photos:</label>
                                <input type="file" name="photos[]" id="photos" multiple accept=".jpg,.jpeg,.png,.gif" required>
                                <small style="color: #666; display: block; margin-top: 5px;">
                                    💡 You can select multiple photos by holding Ctrl (Windows) or Command (Mac) while clicking
                                </small>
                            </div>
                            <button type="submit" class="btn btn-success">📤 Upload Selected Photos</button>
                        </form>
                    </div>
                </div>
                
                <div id="import" class="tab-content">
                    <div class="instructions">
                        <h3>📁 Import from Existing Folder</h3>
                        <p>Import photos that are already in the <code>uploads/pending/</code> folder.</p>
                        
                        <div style="text-align: center; margin: 15px 0;">
                            <a href="?import=1" class="btn btn-info">📁 Import Photos from Folder</a>
                        </div>
                    </div>
                </div>
                
                <div id="manage" class="tab-content">
                    <h3>📋 Manage Photos</h3>
                    
                    <?php
                    // Show current photos list
                    try {
                        $stmt = $pdo->prepare("SELECT photo_id, file_path, assigned FROM unassigned_photos ORDER BY photo_id");
                        $stmt->execute();
                        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($photos) > 0) {
                            echo "<div class='photos-list'>";
                            echo "<h4>Current Photos (" . count($photos) . ")</h4>";
                            foreach ($photos as $photo) {
                                $status = $photo['assigned'] ? '✅ Assigned' : '⏳ Available';
                                $statusClass = $photo['assigned'] ? 'status-assigned' : 'status-available';
                                
                                echo "<div class='photo-item'>";
                                echo "<div class='photo-info'>";
                                echo "<strong>{$photo['photo_id']}</strong><br>";
                                echo "<small style='color: #666;'>{$photo['file_path']}</small><br>";
                                echo "<span class='$statusClass'>$status</span>";
                                echo "</div>";
                                echo "<div class='photo-actions'>";
                                if (!$photo['assigned']) {
                                    echo "<a href='?delete_photo={$photo['photo_id']}' class='btn btn-danger' onclick='return confirm(\"Delete photo {$photo['photo_id']}? This cannot be undone.\")'>🗑️ Delete</a>";
                                }
                                echo "</div>";
                                echo "</div>";
                            }
                            echo "</div>";
                        } else {
                            echo "<p style='text-align: center; color: #666; padding: 20px;'>No photos found in database.</p>";
                        }
                    } catch (PDOException $e) {
                        showMessage("Error loading photos: " . $e->getMessage(), 'error');
                    }
                    ?>
                    
                    <div class="danger-zone">
                        <h3>⚠️ Danger Zone</h3>
                        <p>These actions cannot be undone. Use with caution.</p>
                        <a href="?delete_all=1" class="btn btn-danger" onclick="return confirm('⚠️ WARNING: This will delete ALL photos from the database and server. This action cannot be undone! Are you absolutely sure?')">
                            🗑️ Delete All Photos
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="instructions">
                <h3>📋 Photo Import Instructions</h3>
                <p><strong>Naming Convention:</strong></p>
                <ul>
                    <li>Name photos with identifiers like: <code>IMG_001.jpg</code>, <code>IMG_002.jpg</code>, etc.</li>
                    <li>Photo IDs will be generated from filenames (without extension)</li>
                    <li>Supported formats: JPG, JPEG, PNG, GIF</li>
                </ul>
            </div>
            
            <div style="text-align: center; margin: 25px 0;">
                <a href="../" class="btn">🏠 Back to Main System</a>
                <a href="import_excel.php" class="btn">📊 Import Students</a>
            </div>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show the selected tab
            document.getElementById(tabName).classList.add('active');
            
            // Activate the clicked button
            event.currentTarget.classList.add('active');
        }
        
        // Show file count when files are selected
        document.getElementById('photos').addEventListener('change', function(e) {
            const fileCount = e.target.files.length;
            if (fileCount > 0) {
                const label = document.querySelector('label[for="photos"]');
                label.innerHTML = `Select Photos (${fileCount} selected)`;
            }
        });
    </script>
</body>
</html>