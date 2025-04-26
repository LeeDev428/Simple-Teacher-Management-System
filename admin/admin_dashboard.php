<?php
session_start();
include '../middleware.php';
adminOnly(); // Ensure only admins can access this page
include '../database/db_connection.php';

// Get counts for dashboard stats
$teacherCountQuery = "SELECT COUNT(*) as count FROM teachers";
$teacherResult = $conn->query($teacherCountQuery);
$teacherCount = $teacherResult->fetch_assoc()['count'];

$todayAttendanceQuery = "SELECT COUNT(*) as count FROM attendance WHERE date = CURDATE()";
$todayAttendanceResult = $conn->query($todayAttendanceQuery);
$todayAttendanceCount = $todayAttendanceResult->fetch_assoc()['count'];

// Check if salary_calculations table exists
$tableExistsQuery = "SHOW TABLES LIKE 'salary_calculations'";
$tableResult = $conn->query($tableExistsQuery);
$totalSalary = 0;

if ($tableResult && $tableResult->num_rows > 0) {
    // Get current month's calculated salary
    $month = date('Y-m');
    $salaryQuery = "SELECT SUM(total_amount) as total FROM salary_calculations 
                  WHERE DATE_FORMAT(date, '%Y-%m') = ?";
    $stmt = $conn->prepare($salaryQuery);
    $stmt->bind_param("s", $month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $totalSalary = $result->fetch_assoc()['total'] ?: 0;
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
    <meta name="description" content="Admin Dashboard for Teacher Management System">
    <meta name="author" content="School Administration">
    <meta name="theme-color" content="#4F46E5">
    <title>Admin Dashboard | School Management System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
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
                    <h1 class="text-xl font-bold text-indigo-800">Admin Dashboard</h1>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500">Welcome, Admin</span>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h2 class="font-semibold text-gray-600">Total Teachers</h2>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $teacherCount; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h2 class="font-semibold text-gray-600">Today's Attendance</h2>
                                <p class="text-2xl font-bold text-gray-800"><?php echo $todayAttendanceCount; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h2 class="font-semibold text-gray-600">Total Salary Budget</h2>
                                <p class="text-2xl font-bold text-gray-800">$<?php echo number_format($totalSalary, 2); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Access -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="font-bold text-lg text-gray-800">Quick Access</h2>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <a href="teachers.php" class="bg-indigo-50 hover:bg-indigo-100 p-4 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span class="text-indigo-800 font-medium">Manage Teachers</span>
                        </a>
                        <a href="salaries.php" class="bg-green-50 hover:bg-green-100 p-4 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <span class="text-green-800 font-medium">Manage Salaries</span>
                        </a>
                        <a href="attendance_monitor.php" class="bg-blue-50 hover:bg-blue-100 p-4 rounded-lg flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                            </svg>
                            <span class="text-blue-800 font-medium">Monitor Attendance</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="font-bold text-lg text-gray-800">System Overview</h2>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-700 mb-4">Welcome to the Teacher Management System admin panel. From here you can:</p>
                        <ul class="list-disc pl-5 text-gray-700 space-y-2">
                            <li>View and manage teacher profiles</li>
                            <li>Assign and update teacher salaries</li>
                            <li>Monitor daily attendance records</li>
                            <li>Manage system settings and user accounts</li>
                        </ul>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarElement = document.getElementById('sidebar');
            const openButton = document.getElementById('openSidebar');
            const closeButton = document.getElementById('closeSidebar');
            
            function toggleSidebar() {
                if (sidebarElement) {
                    sidebarElement.classList.toggle('-translate-x-full');
                }
            }
            
            if (openButton) openButton.addEventListener('click', toggleSidebar);
            if (closeButton) closeButton.addEventListener('click', toggleSidebar);
        });
    </script>
</body>
</html>
