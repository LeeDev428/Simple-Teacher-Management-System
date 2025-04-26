<?php
session_start();
include '../middleware.php'; // Include the middleware
userOnly(); // Restrict access to user-only pages
include '../database/db_connection.php'; // Include the database connection

$userId = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

// Get teacher ID
$query = "SELECT id FROM teachers WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $errorMessage = "You need to create a teacher profile first.";
    $teacherId = null;
} else {
    $teacherId = $result->fetch_assoc()['id'];
}
$stmt->close();

// Get all subjects
$subjects = [];
$query = "SELECT id, name, code FROM subjects ORDER BY name";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}

// Handle new assignment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment']) && $teacherId) {
    $subject = $_POST['subject']; // Changed from subject_id to subject (text input)
    $title = $_POST['title'];
    $description = $_POST['description'];
    $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : NULL;
    
    // First, insert or find the subject in the subjects table
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
    
    // Insert assignment with the subject_id
    $query = "INSERT INTO assignments (teacher_id, subject_id, title, description, due_date) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iisss", $teacherId, $subjectId, $title, $description, $dueDate);
    
    if ($stmt->execute()) {
        $assignmentId = $stmt->insert_id;
        $stmt->close();
        
        // Create upload directory if it doesn't exist
        $uploadDir = '../uploads/assignments/' . $assignmentId . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Handle file uploads
        if (!empty($_FILES['attachment']['name'][0])) {
            foreach ($_FILES['attachment']['name'] as $key => $filename) {
                if ($_FILES['attachment']['error'][$key] === 0) {
                    $tmpFilePath = $_FILES['attachment']['tmp_name'][$key];
                    $fileType = $_FILES['attachment']['type'][$key];
                    
                    // Set file title - either use provided title or generate from filename
                    // Fixing the parse error by ensuring variable is properly defined
                    $fileTitle = "";
                    if (isset($_POST['attachment_title'][$key]) && !empty($_POST['attachment_title'][$key])) {
                        $fileTitle = $_POST['attachment_title'][$key];
                    } else {
                        $fileTitle = pathinfo($filename, PATHINFO_FILENAME);
                    }
                    
                    // Generate unique filename
                    $newFilename = uniqid() . '_' . $filename;
                    $filePath = $uploadDir . $newFilename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($tmpFilePath, $filePath)) {
                        // Store file info in database
                        $dbFilePath = 'uploads/assignments/' . $assignmentId . '/' . $newFilename;
                        $query = "INSERT INTO assignment_attachments (assignment_id, title, file_path, file_type, original_filename) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($query);
                        $stmt->bind_param("issss", $assignmentId, $fileTitle, $dbFilePath, $fileType, $filename);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            }
        }
        $successMessage = "Assignment created successfully!";
    } else {
        $errorMessage = "Error creating assignment: " . $conn->error;
    }
}
// Get all assignments for this teacher
$assignments = [];
if ($teacherId) {
    $query = "SELECT a.*, s.name as subject_name 
              FROM assignments a 
              JOIN subjects s ON a.subject_id = s.id 
              WHERE a.teacher_id = ? 
              ORDER BY a.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject and Class Assignments</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }
        // Add more attachment fields
        function addAttachmentField() {
            const container = document.getElementById('attachmentsContainer');
            const index = document.querySelectorAll('.attachment-field').length;
            const newField = document.createElement('div');
            newField.className = 'attachment-field grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-gray-50 rounded mb-3';
            newField.innerHTML = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Attachment Title</label>
                    <input type="text" name="attachment_title[]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">File</label>
                    <input type="file" name="attachment[]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            `;
            
            container.appendChild(newField);
        }
        document.addEventListener('DOMContentLoaded', function() {
            // Setup event listeners
            document.getElementById('openSidebar').addEventListener('click', toggleSidebar);
            document.getElementById('closeSidebar').addEventListener('click', toggleSidebar);
            document.getElementById('addAttachment').addEventListener('click', addAttachmentField);
        });
    </script>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <!-- Sidebar (initially hidden on mobile) -->
        <div id="sidebar" class="lg:block">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="flex-1 lg:ml-64">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm">
                <div class="flex items-center justify-between p-4">
                    <button id="openSidebar" class="lg:hidden text-indigo-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="text-xl font-bold text-indigo-800">Subject and Class Assignments</h1>
                    <div><!-- Placeholder for alignment --></div>
                </div>
            </header>
            <!-- Main Content -->
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
                <!-- Create Assignment Form -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4">Create New Assignment</h2>
                    <form method="post" action="" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="subject">Subject</label>
                                <input type="text" id="subject" name="subject" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="title">Assignment Title</label>
                                <input type="text" id="title" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="description">Description</label>
                                <textarea id="description" name="description" rows="4" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="due_date">Due Date</label>
                                <input type="date" id="due_date" name="due_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        <h3 class="font-medium text-gray-700 mb-2">Attachments</h3>
                        <div id="attachmentsContainer">
                            <div class="attachment-field grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-gray-50 rounded mb-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Attachment Title</label>
                                    <input type="text" name="attachment_title[]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                </div>
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
                        
                        <div>
                            <button type="submit" name="create_assignment" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Create Assignment
                            </button>
                        </div>
                    </form>
                </div>
                <!-- Existing Assignments -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="font-bold text-lg text-gray-800">Your Assignments</h2>
                    </div>
                    <div class="p-6">
                        <?php if (empty($assignments)): ?>
                            <p class="text-gray-500 text-center py-4">No assignments yet. Create your first assignment above.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($assignments as $assignment): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($assignment['subject_name'], ENT_QUOTES, 'UTF-8'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8'); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo !empty($assignment['due_date']) ? date('M d, Y', strtotime($assignment['due_date'])) : 'No deadline'; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo date('M d, Y', strtotime($assignment['created_at'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <a href="view_assignment.php?id=<?php echo $assignment['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">View</a>
                                                    <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="text-green-600 hover:text-green-900">Edit</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
