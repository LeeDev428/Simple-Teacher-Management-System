<?php
session_start();
include '../middleware.php'; // Include the middleware
userOnly(); // Restrict access to user-only pages
include '../database/db_connection.php'; // Include the database connection

$userId = $_SESSION['user_id'];
$assignmentId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($assignmentId === 0) {
    header('Location: assignments.php');
    exit;
}

// Verify that this assignment belongs to the current teacher
$teacherQuery = "SELECT t.id FROM teachers t WHERE t.user_id = ?";
$stmt = $conn->prepare($teacherQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header('Location: dashboard.php');
    exit;
}
$teacherId = $result->fetch_assoc()['id'];
$stmt->close();

// Get assignment details
$query = "SELECT a.*, s.name as subject_name 
          FROM assignments a 
          JOIN subjects s ON a.subject_id = s.id 
          WHERE a.id = ? AND a.teacher_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $assignmentId, $teacherId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header('Location: assignments.php');
    exit;
}
$assignment = $result->fetch_assoc();
$stmt->close();

// Get assignment attachments
$attachments = [];
$query = "SELECT * FROM assignment_attachments WHERE assignment_id = ? ORDER BY uploaded_at";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $assignmentId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $attachments[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Assignment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Setup event listeners
            document.getElementById('openSidebar').addEventListener('click', toggleSidebar);
            document.getElementById('closeSidebar').addEventListener('click', toggleSidebar);
        });
    </script>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <!-- Sidebar (initially hidden on mobile) -->
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
                    <h1 class="text-xl font-bold text-indigo-800">View Assignment</h1>
                    <a href="assignments.php" class="text-indigo-600 hover:text-indigo-800">
                        Back to Assignments
                    </a>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-6">
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h2 class="font-bold text-xl text-gray-800"><?php echo htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <span class="px-3 py-1 bg-indigo-100 text-indigo-800 rounded-full text-sm font-medium">
                                <?php echo htmlspecialchars($assignment['subject_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="mb-6">
                            <div class="flex justify-between mb-4">
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Created:</span>
                                    <span class="text-sm text-gray-900 ml-1"><?php echo date('M d, Y', strtotime($assignment['created_at'])); ?></span>
                                </div>
                                <div>
                                    <span class="text-sm font-medium text-gray-500">Due Date:</span>
                                    <span class="text-sm text-gray-900 ml-1">
                                        <?php echo !empty($assignment['due_date']) ? date('M d, Y', strtotime($assignment['due_date'])) : 'No deadline'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <h3 class="text-md font-medium text-gray-700 mb-2">Description</h3>
                            <div class="bg-gray-50 p-4 rounded">
                                <p class="text-gray-800 whitespace-pre-line"><?php echo htmlspecialchars($assignment['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                        
                        <h3 class="text-md font-medium text-gray-700 mb-2">Attachments</h3>
                        <?php if (empty($attachments)): ?>
                            <p class="text-gray-500 italic">No attachments for this assignment.</p>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-2">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="border rounded-lg overflow-hidden">
                                        <?php if (strpos($attachment['file_type'], 'image/') === 0): ?>
                                            <div class="h-32 bg-gray-200 flex items-center justify-center overflow-hidden">
                                                <img src="../<?php echo htmlspecialchars($attachment['file_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($attachment['title'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-full object-cover">
                                            </div>
                                        <?php elseif ($attachment['file_type'] === 'application/pdf'): ?>
                                            <div class="h-32 bg-red-100 flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                        <?php else: ?>
                                            <div class="h-32 bg-gray-100 flex items-center justify-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        <div class="p-3">
                                            <h4 class="font-medium text-gray-800 truncate"><?php echo htmlspecialchars($attachment['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                            <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($attachment['original_filename'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <a href="../<?php echo htmlspecialchars($attachment['file_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="mt-2 inline-block text-sm text-indigo-600 hover:text-indigo-800">
                                                Download
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
