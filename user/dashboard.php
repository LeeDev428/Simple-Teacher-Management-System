<?php
session_start();
include '../middleware.php'; // Include the middleware
userOnly(); // Restrict access to user-only pages
include '../database/db_connection.php'; // Include the database connection

// Get teacher information
$userId = $_SESSION['user_id'];
$query = "SELECT * FROM teachers WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$teacherExists = $result->num_rows > 0;
$teacher = $teacherExists ? $result->fetch_assoc() : null;
$stmt->close();

// Get recent assignments
$recentAssignments = [];
$assignmentCount = 0;
if ($teacherExists) {
    // Count all assignments
    $countQuery = "SELECT COUNT(*) as total FROM assignments WHERE teacher_id = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $teacher['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignmentCount = $result->fetch_assoc()['total'];
    $stmt->close();
    
    // Get 5 most recent assignments
    $query = "SELECT a.*, s.name as subject_name 
              FROM assignments a 
              JOIN subjects s ON a.subject_id = s.id 
              WHERE a.teacher_id = ? 
              ORDER BY a.created_at DESC LIMIT 5";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $recentAssignments[] = $row;
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
    <meta name="description" content="Teacher Management System Dashboard">
    <meta name="author" content="School Administration">
    <meta name="theme-color" content="#4F46E5">
    <title>Teacher Dashboard | School Management System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <!-- Custom JavaScript -->
    <script>
        /**
         * Handle sidebar toggle functionality
         * Controls mobile responsiveness for the sidebar component
         */
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle elements
            const sidebarElement = document.getElementById('sidebar');
            const openButton = document.getElementById('openSidebar');
            const closeButton = document.getElementById('closeSidebar');
            
            // Toggle sidebar visibility
            function toggleSidebar() {
                if (sidebarElement) {
                    sidebarElement.classList.toggle('-translate-x-full');
                }
            }
            
            // Close sidebar when clicking outside on mobile
            function setupOutsideClickHandler() {
                document.addEventListener('click', function(event) {
                    const isMobile = window.innerWidth < 1024; // lg breakpoint
                    if (isMobile && sidebarElement && !sidebarElement.contains(event.target) && 
                        openButton && !openButton.contains(event.target)) {
                        sidebarElement.classList.add('-translate-x-full');
                    }
                });
            }
            
            // Setup event listeners
            if (openButton) openButton.addEventListener('click', toggleSidebar);
            if (closeButton) closeButton.addEventListener('click', toggleSidebar);
            
            // Initialize outside click handler
            setupOutsideClickHandler();
            
            // Handle resize events
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 1024) { // lg breakpoint
                    sidebarElement.classList.remove('-translate-x-full');
                }
            });
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
                    <h1 class="text-xl font-bold text-indigo-800">Dashboard</h1>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500">Welcome, <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="p-6">
                <?php if (!$teacherExists): ?>
                    <!-- No teacher profile yet -->
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    Your teacher profile is not set up yet. <a href="teacher_profile.php" class="font-medium underline text-yellow-700 hover:text-yellow-600">Click here to create your profile</a>.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h2 class="font-semibold text-gray-600">Teacher Profile</h2>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $teacherExists ? 'Complete' : 'Incomplete'; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h2 class="font-semibold text-gray-600">Subject</h2>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $teacherExists ? htmlspecialchars($teacher['subject'], ENT_QUOTES, 'UTF-8') : 'Not assigned'; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h2 class="font-semibold text-gray-600">Assignments</h2>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $assignmentCount; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Teacher Profile Information -->
                <?php if ($teacherExists): ?>
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="font-bold text-lg text-gray-800">Teacher Profile Information</h2>
                        <a href="teacher_profile.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                            Edit Profile
                        </a>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Full Name</h3>
                                <p class="mt-1 text-lg font-medium text-gray-900"><?php echo htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Email</h3>
                                <p class="mt-1 text-lg font-medium text-gray-900"><?php echo htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Phone</h3>
                                <p class="mt-1 text-lg font-medium text-gray-900"><?php echo htmlspecialchars($teacher['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Subject</h3>
                                <p class="mt-1 text-lg font-medium text-gray-900"><?php echo htmlspecialchars($teacher['subject'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Qualification</h3>
                                <p class="mt-1 text-lg font-medium text-gray-900"><?php echo htmlspecialchars($teacher['qualification'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Joining Date</h3>
                                <p class="mt-1 text-lg font-medium text-gray-900">
                                    <?php echo !empty($teacher['joining_date']) ? date('m/d/Y', strtotime($teacher['joining_date'])) : 'Not set'; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Assignments Section -->
                <?php if (!empty($recentAssignments)): ?>
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="font-bold text-lg text-gray-800">Recent Activities</h2>
                        <a href="assignments.php" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">
                            View All Assignments
                        </a>
                    </div>
                    <div class="p-6">
                        <ul class="divide-y divide-gray-200">
                            <?php foreach ($recentAssignments as $assignment): ?>
                                <li class="py-3">
                                    <div class="flex justify-between">
                                        <div>
                                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($assignment['title'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="text-gray-500"> in <?php echo htmlspecialchars($assignment['subject_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="flex space-x-2">
                                            <span class="text-sm text-gray-400"><?php echo date('M d, Y', strtotime($assignment['created_at'])); ?></span>
                                            <a href="view_assignment.php?id=<?php echo $assignment['id']; ?>" class="text-indigo-600 hover:text-indigo-900 text-sm">View</a>
                                        </div>
                                    </div>
                                    <?php if (!empty($assignment['due_date'])): ?>
                                    <div class="mt-1">
                                        <span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded">Due: <?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
