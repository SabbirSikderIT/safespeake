<?php
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json');

$studentId = $_GET['id'] ?? '';
$studentId = trim($studentId);

if (empty($studentId)) {
    echo json_encode(['found' => false]);
    exit;
}

$student = getStudentFromDirectory($studentId);

if ($student) {
    echo json_encode([
        'found' => true,
        'student_id' => $student['student_id'],
        'full_name' => $student['full_name'],
        'email' => $student['email'],
        'department' => $student['department'],
        'gpa' => $student['gpa'],
        'graduation_year' => $student['graduation_year']
    ]);
} else {
    echo json_encode(['found' => false]);
}
?>