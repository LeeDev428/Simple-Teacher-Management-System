<?php
session_start();
include '../middleware.php';
userOnly();
include '../database/db_connection.php';

$userId = $_SESSION['user_id'];
$assignmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$successMessage = '';
$errorMessage = '';

// Get teacher ID
$query = "SELECT id FROM teachers WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header("Location: dashboard.php");
    exit();
}
$teacherId = $result->fetch_assoc()['id'];
$stmt->close();

// Check if assignment exists and belongs to the teacher
$query = "SELECT a.*, s.name as subject_name 
          FROM assignments a 
          JOIN subjects s ON a.subject_id = s.id 
          WHERE a.id = ? AND a.teacher_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $assignmentId, $teacherId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header("Location: assignments.php");
    exit();
}
$assignment = $result->fetch_assoc();
$stmt->close();

// Get current attachments
$attachments = [];
$query = "SELECT * FROM assignment_attachments WHERE assignment_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $assignmentId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $attachments[] = $row;
}
$stmt->close();

// Handle update request (PATCH)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assignment'])) {
    $subject = $_POST['subject'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : NULL;
    
    // Check if subject exists or create it
    $query = "SELECT id FROM subjects WHERE name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $subject);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Subject exists, get its ID
        $subjectId = $result->fetch_assoc()['id'];
    } else {
        // Create new subject
        $subjectCode = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $subject), 0, 10));
        $insertQuery = "INSERT INTO subjects (name, code) VALUES (?, ?)";
        $stmt = $conn->prepare($insertQuery);
        $stmt->bind_param("ss", $subject, $subjectCode);
        $stmt->execute();
        $subjectId = $stmt->insert_id;
        $stmt->close();
    }
    
    // Update assignment
    $query = "UPDATE assignments SET subject_id = ?, title = ?, description = ?, due_date = ? WHERE id = ? AND teacher_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isssii", $subjectId, $title, $description, $dueDate, $assignmentId, $teacherId);
    
    if ($stmt->execute()) {
        // Handle new file uploads
        if (!empty($_FILES['attachment']['name'][0])) {
            $uploadDir = '../uploads/assignments/' . $assignmentId . '/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            foreach ($_FILES['attachment']['name'] as $key => $filename) {
                if ($_FILES['attachment']['error'][$key] === 0) {
                    $tmpFilePath = $_FILES['attachment']['tmp_name'][$key];
                    $fileType = $_FILES['attachment']['type'][$key];
                    $fileTitle = pathinfo($filename, PATHINFO_FILENAME);
                    
                    $newFilename = uniqid() . '_' . $filename;
                    $filePath = $uploadDir . $newFilename;
                    
                    if (move_uploaded_file($tmpFilePath, $filePath)) {
                        $dbFilePath = 'uploads/assignments/' . $assignmentId . '/' . $newFilename;
                        $query = "INSERT INTO assignment_attachments (assignment_id, title, file_path, file_type, original_filename) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("issss", $assignmentId, $fileTitle, $dbFilePath, $fileType, $filename);
                        $stmt->execute();
                    }
                }
            }
        }
        
        // Handle attachment deletions
        if (isset($_POST['delete_attachment'])) {
            foreach ($_POST['delete_attachment'] as $attachmentId) {
                // Get file path before deleting record
                $query = "SELECT file_path FROM assignment_attachments WHERE id = ? AND assignment_id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $attachmentId, $assignmentId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $filePath = '../' . $result->fetch_assoc()['file_path'];
                    
                    // Delete record from database
                    $query = "DELETE FROM assignment_attachments WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("i", $attachmentId);
                    $stmt->execute();
                    
                    // Delete file if exists
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
            }
        }
        
        $successMessage = "Assignment updated successfully!";
        
        // Refresh assignment data
        $query = "SELECT a.*, s.name as subject_name 
                 FROM assignments a 
                 JOIN subjects s ON a.subject_id = s.id 
                 WHERE a.id = ? AND a.teacher_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $assignmentId, $teacherId);
        $stmt->execute();
        $assignment = $stmt->get_result()->fetch_assoc();
        
        // Refresh attachments
        $attachments = [];
        $query = "SELECT * FROM assignment_attachments WHERE assignment_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $assignmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $attachments[] = $row;
        }
    } else {
        $errorMessage = "Error updating assignment: " . $conn->error;
    }
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_assignment'])) {
    // First delete all attachments
    $query = "SELECT file_path FROM assignment_attachments WHERE assignment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $assignmentId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $filePath = '../' . $row['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    // Delete all attachment records
    $query = "DELETE FROM assignment_attachments WHERE assignment_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $assignmentId);
    $stmt->execute();
    
    // Delete assignment
    $query = "DELETE FROM assignments WHERE id = ? AND teacher_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $assignmentId, $teacherId);
    
    if ($stmt->execute()) {
        // Try to remove the directory
        $uploadDir = '../uploads/assignments/' . $assignmentId . '/';
        if (is_dir($uploadDir)) {
            rmdir($uploadDir); // This will only work if the directory is empty
        }
        
        header("Location: assignments.php?success=deleted");
        exit();
    } else {
        $errorMessage = "Error deleting assignment: " . $conn->error;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assignment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        function addAttachmentField() {
            const container = document.getElementById('attachmentsContainer');
            const newField = document.createElement('div');
            newField.className = 'attachment-field grid grid-cols-1 gap-4 p-4 bg-gray-50 rounded mb-3';
            newField.innerHTML = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">File</label>
                    <input type="file" name="attachment[]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            `;
            
            container.appendChild(newField);
        }

        function confirmDelete() {
            return confirm('Are you sure you want to delete this assignment? This action cannot be undone.');
        }

        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('openSidebar').addEventListener('click', toggleSidebar);
            document.getElementById('closeSidebar').addEventListener('click', toggleSidebar);
            document.getElementById('addAttachment').addEventListener('click', addAttachmentField);
        });
    </script>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="lg:block">
            <?php include 'sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-64">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm">
                <div class="flex items-center justify-between p-4">
                    <button id="openSidebar" class="lg:hidden text-indigo-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="text-xl font-bold text-indigo-800">Edit Assignment</h1>
                    <a href="assignments.php" class="text-indigo-600 hover:text-indigo-800">
                        Back to Assignments
                    </a>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-6">
                <?php if ($successMessage): ?>
                    <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-green-700"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($errorMessage): ?>
                    <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-red-700"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Edit Assignment Form -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Edit Assignment</h2>
                    <form method="post" action="" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="subject">Subject</label>
                                <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($assignment['subject_name'], ENT_QUOTES, 'UTF-8'); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="title">Assignment Title</label>
                                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8'); ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="description">Description</label>
                                <textarea id="description" name="description" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($assignment['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="due_date">Due Date</label>
                                <input type="date" id="due_date" name="due_date" value="<?php echo $assignment['due_date'] ? date('Y-m-d', strtotime($assignment['due_date'])) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <!-- Current Attachments -->
                        <?php if (!empty($attachments)): ?>
                            <h3 class="font-medium text-gray-700 mb-2">Current Attachments</h3>
                            <div class="mb-6">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="flex items-center justify-between p-3 border rounded-md mb-2">
                                        <div class="flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z" clip-rule="evenodd" />
                                            </svg>
                                            <a href="../<?php echo htmlspecialchars($attachment['file_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800">
                                                <?php echo htmlspecialchars($attachment['original_filename'], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                        </div>
                                        <div class="flex items-center">
                                            <label class="inline-flex items-center">
                                                <input type="checkbox" name="delete_attachment[]" value="<?php echo $attachment['id']; ?>" class="h-4 w-4 text-red-600 border-gray-300 rounded">
                                                <span class="ml-2 text-sm text-red-600">Delete</span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <h3 class="font-medium text-gray-700 mb-2">Add New Attachments</h3>
                        <div id="attachmentsContainer">
                            <div class="attachment-field grid grid-cols-1 gap-4 p-4 bg-gray-50 rounded mb-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">File</label>
                                    <input type="file" name="attachment[]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-between mb-6">
                            <button type="button" id="addAttachment" class="text-indigo-600 hover:text-indigo-800">
                                + Add Another Attachment
                            </button>
                        </div>
                        
                        <div class="flex justify-between">
                            <button type="submit" name="update_assignment" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Update Assignment
                            </button>
                            
                            <button type="submit" name="delete_assignment" onclick="return confirmDelete()" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                Delete Assignment
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
