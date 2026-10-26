<?php
require_once __DIR__ . '/includes/config.php';

echo "<h2>Importing Students from CSV</h2>";

$csvFile = __DIR__ . '/../database/studentdata.csv';

if (!file_exists($csvFile)) {
    die("Error: CSV file not found at " . $csvFile);
}

$db = getDB();

// First, clear existing data (optional - comment out if you want to keep existing)
// $db->exec("TRUNCATE TABLE student_directory");

$row = 1;
$imported = 0;
$skipped = 0;
$errors = [];

if (($handle = fopen($csvFile, "r")) !== false) {
    // Skip header row
    $headers = fgetcsv($handle);
    
    while (($data = fgetcsv($handle)) !== false) {
        $student_id = trim($data[0] ?? '');
        $full_name = trim($data[1] ?? '');
        $age = !empty($data[2]) ? (int)$data[2] : null;
        $email = trim($data[3] ?? '');
        $department = trim($data[4] ?? '');
        $gpa = !empty($data[5]) ? (float)$data[5] : null;
        $grad_year = !empty($data[6]) ? (int)$data[6] : null;
        
        if (empty($student_id) || empty($full_name) || empty($email)) {
            $skipped++;
            $errors[] = "Row $row: Missing required fields (ID, Name, or Email)";
            $row++;
            continue;
        }
        
        try {
            // Check if student already exists
            $check = $db->prepare("SELECT id FROM student_directory WHERE student_id = ?");
            $check->execute([$student_id]);
            
            if ($check->fetch()) {
                // Update existing record
                $stmt = $db->prepare("UPDATE student_directory SET 
                    full_name = ?, age = ?, email = ?, department = ?, 
                    gpa = ?, graduation_year = ? WHERE student_id = ?");
                $stmt->execute([$full_name, $age, $email, $department, $gpa, $grad_year, $student_id]);
                echo "Updated: $student_id - $full_name<br>";
            } else {
                // Insert new record
                $stmt = $db->prepare("INSERT INTO student_directory 
                    (student_id, full_name, age, email, department, gpa, graduation_year) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $full_name, $age, $email, $department, $gpa, $grad_year]);
                echo "Imported: $student_id - $full_name<br>";
            }
            $imported++;
        } catch (PDOException $e) {
            $errors[] = "Row $row: " . $e->getMessage();
            $skipped++;
        }
        
        $row++;
    }
    fclose($handle);
}

echo "<hr>";
echo "<h3>Import Summary:</h3>";
echo "<ul>";
echo "<li>Total rows processed: " . ($row - 1) . "</li>";
echo "<li>Successfully imported/updated: $imported</li>";
echo "<li>Skipped: $skipped</li>";
echo "</ul>";

if (!empty($errors)) {
    echo "<h3>Errors:</h3><ul>";
    foreach ($errors as $err) {
        echo "<li>" . htmlspecialchars($err) . "</li>";
    }
    echo "</ul>";
}

echo "<p><a href='admin/dashboard.php'>Go to Admin Panel</a></p>";
?>