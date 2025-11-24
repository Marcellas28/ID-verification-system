<?php
/**
 * KMTC Student Data Excel Import Script
 * Usage: http://localhost/kmtc-photo-system/scripts/import_excel.php
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'kmtc_photo_system');
define('DB_USER', 'root');
define('DB_PASS', '');

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

function readCSVFile($filename) {
    $data = [];
    if (($handle = fopen($filename, "r")) !== FALSE) {
        $headers = [];
        $rowCount = 0;
        
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($rowCount === 0) {
                // First row - headers
                $headers = array_map('trim', $row);
            } else {
                // Data rows
                $dataRow = [];
                foreach ($headers as $index => $header) {
                    $dataRow[$header] = $row[$index] ?? '';
                }
                $data[] = $dataRow;
            }
            $rowCount++;
        }
        fclose($handle);
    }
    return $data;
}

function mapColumnsToFields($headers) {
    $mapping = [];
    $possibleFields = [
        'student_id' => ['student_id', 'studentid', 'id', 'student id', 'student number', 'student_no', 'student id'],
        'full_name' => ['full_name', 'fullname', 'name', 'student name', 'student_name', 'full name', 'student name'],
        'registration_no' => ['registration_no', 'registrationno', 'reg_no', 'reg no', 'registration number', 'registration', 'reg number']
    ];
    
    foreach ($headers as $index => $header) {
        $headerLower = strtolower(trim($header));
        
        foreach ($possibleFields as $field => $variations) {
            foreach ($variations as $variation) {
                if ($headerLower === strtolower($variation)) {
                    $mapping[$field] = $header;
                    break 2;
                }
            }
        }
    }
    
    return $mapping;
}

function importMappedData($data, $mapping, $pdo) {
    $imported = 0;
    $updated = 0;
    $errors = 0;
    
    echo "<h4>Importing Records:</h4>";
    
    // Show mapping results
    echo "<div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 20px;'>";
    echo "<strong>Field Mapping Detected:</strong><br>";
    foreach (['student_id', 'full_name', 'registration_no'] as $field) {
        if (isset($mapping[$field])) {
            showMessage("$field → " . $mapping[$field], 'success');
        } else {
            showMessage("$field → NOT FOUND", 'error');
        }
    }
    echo "</div>";
    
    foreach ($data as $index => $row) {
        echo "<div style='margin: 5px 0; padding: 10px; background: #f8f9fa; border-radius: 3px;'>";
        echo "<strong>Processing row " . ($index + 1) . ":</strong> ";
        
        // Extract data using mapping
        $studentData = [
            'student_id' => $row[$mapping['student_id']] ?? '',
            'full_name' => $row[$mapping['full_name']] ?? '',
            'registration_no' => $row[$mapping['registration_no']] ?? ''
        ];
        
        // Clean data
        $studentData['student_id'] = strtoupper(trim($studentData['student_id']));
        $studentData['full_name'] = trim($studentData['full_name']);
        $studentData['registration_no'] = trim($studentData['registration_no']);
        
        // Validate required fields
        if (empty($studentData['student_id'])) {
            showMessage("Missing student ID in row " . ($index + 1), 'error');
            $errors++;
            echo "</div>";
            continue;
        }
        
        if (empty($studentData['full_name'])) {
            showMessage("Missing full name for: " . $studentData['student_id'], 'warning');
            $studentData['full_name'] = 'Unknown - ' . $studentData['student_id'];
        }
        
        if (empty($studentData['registration_no'])) {
            showMessage("Missing registration number for: " . $studentData['student_id'], 'warning');
            $studentData['registration_no'] = 'KMTC/' . $studentData['student_id'];
        }
        
        try {
            // Check if student exists
            $checkStmt = $pdo->prepare("SELECT student_id FROM students WHERE student_id = ?");
            $checkStmt->execute([$studentData['student_id']]);
            $exists = $checkStmt->fetch();
            
            if ($exists) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE students SET full_name = ?, registration_no = ? WHERE student_id = ?");
                $stmt->execute([$studentData['full_name'], $studentData['registration_no'], $studentData['student_id']]);
                $updated++;
                showMessage("Updated: {$studentData['student_id']} - {$studentData['full_name']}", 'warning');
            } else {
                // Insert new
                $stmt = $pdo->prepare("INSERT INTO students (student_id, full_name, registration_no) VALUES (?, ?, ?)");
                $stmt->execute([$studentData['student_id'], $studentData['full_name'], $studentData['registration_no']]);
                $imported++;
                showMessage("Imported: {$studentData['student_id']} - {$studentData['full_name']}", 'success');
            }
        } catch (PDOException $e) {
            showMessage("Database error for {$studentData['student_id']}: " . $e->getMessage(), 'error');
            $errors++;
        }
        
        echo "</div>";
    }
    
    // Summary
    echo "<h3>Import Summary</h3>";
    showMessage("Successfully imported: $imported new records", 'success');
    showMessage("Updated: $updated existing records", 'warning');
    showMessage("Errors: $errors records", $errors > 0 ? 'error' : 'success');
    showMessage("Total processed: " . ($imported + $updated + $errors) . " records", 'info');
    
    return $errors === 0;
}

function handleFileUpload($uploadedFile, $pdo) {
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        showMessage("File upload error: " . $uploadedFile['error'], 'error');
        return false;
    }
    
    $uploadDir = '../uploads/';
    $uploadFile = $uploadDir . basename($uploadedFile['name']);
    
    // Ensure upload directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    if (move_uploaded_file($uploadedFile['tmp_name'], $uploadFile)) {
        showMessage("File uploaded successfully: " . $uploadedFile['name'], 'success');
        
        // Get file extension
        $fileExtension = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));
        
        $data = [];
        
        try {
            if ($fileExtension === 'csv') {
                // Handle CSV files
                $data = readCSVFile($uploadFile);
            } else {
                // For Excel files, we'll use CSV approach
                showMessage("Please convert Excel files to CSV format for best compatibility.", 'warning');
                showMessage("You can save Excel files as CSV in Excel: File → Save As → CSV (Comma delimited)", 'info');
                unlink($uploadFile);
                return false;
            }
            
            if (empty($data)) {
                throw new Exception("No data found in the file or file is empty");
            }
            
            showMessage("Successfully read " . count($data) . " rows from file", 'success');
            
            // Display first few rows for verification
            echo "<h4>Sample Data (First 3 Rows):</h4>";
            echo "<div style='overflow-x: auto;'>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%; background: white;'>";
            echo "<thead style='background: #0056A3; color: white;'>";
            if (!empty($data[0])) {
                echo "<tr>";
                foreach (array_keys($data[0]) as $header) {
                    echo "<th style='padding: 10px; text-align: left;'>" . htmlspecialchars($header) . "</th>";
                }
                echo "</tr>";
            }
            echo "</thead>";
            echo "<tbody>";
            for ($i = 0; $i < min(3, count($data)); $i++) {
                echo "<tr>";
                foreach ($data[$i] as $cell) {
                    echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . htmlspecialchars($cell) . "</td>";
                }
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
            echo "</div>";
            
            // Map columns to fields
            $mapping = mapColumnsToFields(array_keys($data[0]));
            
            // Check if we have all required mappings
            $missingFields = [];
            foreach (['student_id', 'full_name', 'registration_no'] as $field) {
                if (!isset($mapping[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                showMessage("Missing required field mappings: " . implode(', ', $missingFields), 'error');
                showMessage("Please check your CSV file has the correct column names.", 'warning');
                unlink($uploadFile);
                return false;
            }
            
            // Start import
            $result = importMappedData($data, $mapping, $pdo);
            
            // Clean up uploaded file
            unlink($uploadFile);
            
            return $result;
            
        } catch (Exception $e) {
            showMessage("Error reading file: " . $e->getMessage(), 'error');
            if (file_exists($uploadFile)) {
                unlink($uploadFile);
            }
            return false;
        }
    } else {
        showMessage("Failed to upload file", 'error');
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KMTC - Excel Data Import</title>
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
        .upload-section {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 5px solid #0056A3;
        }
        .btn {
            background: linear-gradient(135deg, #0056A3, #003366);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            margin: 5px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 86, 163, 0.3);
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        .form-group {
            margin-bottom: 20px;
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
        }
        .instructions {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .instructions h3 {
            color: #856404;
            margin-top: 0;
        }
        .sample-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: white;
        }
        .sample-table th {
            background: #0056A3;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .sample-table td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .sample-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        .download-sample {
            text-align: center;
            margin: 20px 0;
        }
        .current-stats {
            background: #e7f3ff;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>KMTC Student Data Import</h1>
            <div class="subtitle">Import student data from CSV files into the database</div>
        </div>
        
        <div class="content">
            <?php
            // Handle file upload
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excelfile'])) {
                handleFileUpload($_FILES['excelfile'], $pdo);
            }
            
            // Show current database stats
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM students");
                $stmt->execute();
                $studentCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                echo "<div class='current-stats'>";
                echo "<h3>📊 Current Database Status</h3>";
                echo "<p><strong>Total Students in Database:</strong> " . $studentCount . "</p>";
                echo "</div>";
            } catch (PDOException $e) {
                showMessage("Error fetching statistics: " . $e->getMessage(), 'error');
            }
            ?>
            
            <div class="instructions">
                <h3>📋 CSV File Requirements</h3>
                
                <p><strong>Supported Format:</strong> .csv (Comma Separated Values)</p>
                
                <p><strong>Required Columns (any of these names will work):</strong></p>
                <ul>
                    <li><strong>Student ID:</strong> student_id, studentId, ID, Student ID, student number</li>
                    <li><strong>Full Name:</strong> full_name, fullName, Name, Student Name, full name</li>
                    <li><strong>Registration No:</strong> registration_no, registrationNo, reg_no, Registration Number, </li>
                </ul>
                
                <div class="download-sample">
                    <a href="?download_sample=1" class="btn btn-success">📥 Download Sample CSV File</a>
                </div>
            </div>
            
            <div class="upload-section">
                <h3>Upload CSV File</h3>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="excelfile">Select CSV File:</label>
                        <input type="file" name="excelfile" id="excelfile" accept=".csv" required>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            💡 <strong>For Excel files:</strong> Save as CSV in Excel (File → Save As → CSV)
                        </small>
                    </div>
                    <button type="submit" class="btn btn-success">📤 Import Student Data</button>
                </form>
            </div>
            
            <div class="instructions">
                <h3>📊 Sample CSV Structure</h3>
                
                <table class="sample-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Registration No {here input national ID}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>C/OTM/25678/159</td>
                            <td>Marcellas Indeje</td>
                            <td>75821581</td>
                        </tr>
                        <tr>
                            <td>C/OTM/25678/180</td>
                            <td>Bradley Kyle</td>
                            <td>1289542</td>
                        </tr>
                        <tr>
                            <td>C/OTM/28678/189</td>
                            <td>Katana Faiz</td>
                            <td>4586936</td>
                        </tr>
                    </tbody>
                </table>
                
                <p><strong>Tips for creating your CSV:</strong></p>
                <ul>
                    <li>Include headers in the first row</li>
                    <li>Ensure Student IDs are unique</li>
                    <li>N/B : Save as CSV format (not Excel)</li>
                    <li>Remove any empty rows</li>
                    <li>Use consistent column names</li>
                </ul>
            </div>
            
            <div style="margin-top: 30px; text-align: center;">
                <a href="../" class="btn">🏠 Back to Main System</a>
                <a href="import_photos.php" class="btn">📷 Import Photos</a>
            </div>
        </div>
    </div>
</body>
</html>

<?php
// Handle sample file download
if (isset($_GET['download_sample'])) {
    $sampleData = [
        ['Student ID', 'Full Name', 'Registration No'],
        ['C/OTM/25678/159', 'Marcellas Indeje', '75821581'],
        ['C/OTM/25678/180', 'Bradley Kyle', '1289542'],
        ['C/OTM/28678/189', 'Katana Faiz', '4586936']
    ];
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="kmtc_students_sample.csv"');
    
    $output = fopen('php://output', 'w');
    foreach ($sampleData as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}
?>