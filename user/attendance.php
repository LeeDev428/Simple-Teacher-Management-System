<?php
session_start();
include '../middleware.php';
userOnly();
include '../database/db_connection.php';

$userId = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$recordsPerPage = 20;
$offset = ($currentPage - 1) * $recordsPerPage;

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

// If teacher exists, proceed with attendance logic
if ($teacherId) {
    // Handle check-in action
    if (isset($_POST['check_in'])) {
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        // Check if past midnight but user didn't check out from previous day
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $checkYesterdayQuery = "SELECT id, check_in, check_out FROM attendance WHERE teacher_id = ? AND date = ?";
        $stmt = $conn->prepare($checkYesterdayQuery);
        $stmt->bind_param("is", $teacherId, $yesterday);
        $stmt->execute();
        $yesterdayResult = $stmt->get_result();
        
        if ($yesterdayResult->num_rows > 0) {
            $yesterdayRecord = $yesterdayResult->fetch_assoc();
            if ($yesterdayRecord['check_in'] && !$yesterdayRecord['check_out']) {
                // Auto-checkout at 11:59 PM if they forgot to check out
                $yesterdayCheckout = $yesterday . ' 23:59:59';
                $updateQuery = "UPDATE attendance SET check_out = ?, notes = CONCAT(IFNULL(notes, ''), ' (Auto checkout at midnight)') WHERE id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("si", $yesterdayCheckout, $yesterdayRecord['id']);
                $stmt->execute();
            }
        }
        $stmt->close();
        
        // Continue with normal check-in process
        // Check if an attendance record already exists for today
        $query = "SELECT id, check_in FROM attendance WHERE teacher_id = ? AND date = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $teacherId, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $record = $result->fetch_assoc();
            if ($record['check_in']) {
                $errorMessage = "You have already checked in today.";
            } else {
                // Update existing record with check-in time
                $query = "UPDATE attendance SET check_in = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("si", $now, $record['id']);
                if ($stmt->execute()) {
                    $successMessage = "Check-in successful at " . date('h:i A');
                } else {
                    $errorMessage = "Failed to record check-in.";
                }
            }
        } else {
            // Create new attendance record for today
            $query = "INSERT INTO attendance (teacher_id, date, check_in) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iss", $teacherId, $today, $now);
            if ($stmt->execute()) {
                $successMessage = "Check-in successful at " . date('h:i A');
            } else {
                $errorMessage = "Failed to record check-in.";
            }
        }
        $stmt->close();
    }
    
    // Handle check-out action
    if (isset($_POST['check_out'])) {
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        
        // Check if an attendance record exists for today and has check-in
        $query = "SELECT id, check_in, check_out FROM attendance WHERE teacher_id = ? AND date = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("is", $teacherId, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $record = $result->fetch_assoc();
            if (!$record['check_in']) {
                $errorMessage = "You need to check in before checking out.";
            } elseif ($record['check_out']) {
                $errorMessage = "You have already checked out today.";
            } else {
                // Update record with check-out time
                $query = "UPDATE attendance SET check_out = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("si", $now, $record['id']);
                if ($stmt->execute()) {
                    $successMessage = "Check-out successful at " . date('h:i A');
                    
                    // Calculate salary for this attendance if salary rates exist
                    $checkRateQuery = "SELECT id FROM salary_rates WHERE teacher_id = ?";
                    $rateStmt = $conn->prepare($checkRateQuery);
                    $rateStmt->bind_param("i", $teacherId);
                    $rateStmt->execute();
                    $rateResult = $rateStmt->get_result();
                    
                    if ($rateResult->num_rows > 0) {
                        // Get the attendance record id
                        $attendanceQuery = "SELECT id FROM attendance WHERE teacher_id = ? AND date = ?";
                        $attendanceStmt = $conn->prepare($attendanceQuery);
                        $attendanceStmt->bind_param("is", $teacherId, $today);
                        $attendanceStmt->execute();
                        $attendanceResult = $attendanceStmt->get_result();
                        
                        if ($attendanceResult->num_rows > 0) {
                            $attendanceId = $attendanceResult->fetch_assoc()['id'];
                            
                            // Schedule salary calculation (or trigger it via AJAX if needed)
                            $successMessage .= " Your salary will be calculated based on your attendance.";
                        }
                        $attendanceStmt->close();
                    }
                    $rateStmt->close();
                } else {
                    $errorMessage = "Failed to record check-out.";
                }
            }
        } else {
            $errorMessage = "You need to check in first.";
        }
        $stmt->close();
    }
    
    // Get today's attendance status for the current teacher
    $today = date('Y-m-d');
    $todayAttendance = null;
    
    $query = "SELECT check_in, check_out FROM attendance WHERE teacher_id = ? AND date = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $teacherId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $todayAttendance = $result->fetch_assoc();
    }
    $stmt->close();
    
    // Count total records for pagination
    $query = "SELECT COUNT(*) as total FROM attendance WHERE teacher_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $totalRecords = $row['total'];
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $stmt->close();
    
    // Get attendance records with pagination
    $query = "SELECT date, check_in, check_out, status, notes 
              FROM attendance 
              WHERE teacher_id = ? 
              ORDER BY date DESC 
              LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $teacherId, $recordsPerPage, $offset);
    $stmt->execute();
    $attendanceRecords = $stmt->get_result();
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Teacher Attendance Tracking">
    <meta name="author" content="School Administration">
    <meta name="theme-color" content="#4F46E5">
    <title>Attendance Tracking | School Management System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <!-- Custom JavaScript -->
    <script>
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
            
            // Setup event listeners
            if (openButton) openButton.addEventListener('click', toggleSidebar);
            if (closeButton) closeButton.addEventListener('click', toggleSidebar);
            
            // Update clock
            function updateClock() {
                const now = new Date();
                const timeStr = now.toLocaleTimeString();
                const dateStr = now.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                
                document.getElementById('current-time').textContent = timeStr;
                document.getElementById('current-date').textContent = dateStr;
                
                setTimeout(updateClock, 1000);
            }
            
            updateClock();
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
                    <h1 class="text-xl font-bold text-indigo-800">Attendance Tracking</h1>
                    <div><!-- Placeholder for alignment --></div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-6">
                <?php if (!$teacherId): ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    You need to create a teacher profile first. <a href="teacher_profile.php" class="font-medium underline text-yellow-700 hover:text-yellow-600">Click here to create your profile</a>.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Current Date and Time Display -->
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <div class="text-center">
                            <div class="text-4xl font-bold text-indigo-700 mb-2" id="current-time"></div>
                            <div class="text-lg text-gray-600" id="current-date"></div>
                        </div>
                    </div>
                    
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
                    
                    <!-- Check-in and Check-out Buttons -->
                    <div class="bg-white rounded-lg shadow p-6 mb-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-4">Today's Attendance</h2>
                        <div class="flex flex-col md:flex-row space-y-4 md:space-y-0 md:space-x-4">
                            <form method="post" action="" class="flex-1">
                                <button type="submit" name="check_in" 
                                    class="w-full bg-green-600 text-white px-4 py-3 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-300 font-medium
                                    <?php echo isset($todayAttendance) && $todayAttendance['check_in'] ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                    <?php echo isset($todayAttendance) && $todayAttendance['check_in'] ? 'disabled' : ''; ?>>
                                    <?php 
                                    if (isset($todayAttendance) && $todayAttendance['check_in']) {
                                        echo 'Checked In: ' . date('h:i A', strtotime($todayAttendance['check_in']));
                                    } else {
                                        echo 'Check In';
                                    }
                                    ?>
                                </button>
                            </form>
                            
                            <form method="post" action="" class="flex-1">
                                <button type="submit" name="check_out" 
                                    class="w-full bg-blue-600 text-white px-4 py-3 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-300 font-medium
                                    <?php echo !isset($todayAttendance) || !$todayAttendance['check_in'] || (isset($todayAttendance) && $todayAttendance['check_out']) ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                                    <?php echo !isset($todayAttendance) || !$todayAttendance['check_in'] || (isset($todayAttendance) && $todayAttendance['check_out']) ? 'disabled' : ''; ?>>
                                    <?php 
                                    if (isset($todayAttendance) && $todayAttendance['check_out']) {
                                        echo 'Checked Out: ' . date('h:i A', strtotime($todayAttendance['check_out']));
                                    } else {
                                        echo 'Check Out';
                                    }
                                    ?>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Attendance History Table -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="font-bold text-lg text-gray-800">Attendance History</h2>
                        </div>
                        <div class="p-6">
                            <?php if ($attendanceRecords && $attendanceRecords->num_rows > 0): ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check In</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check Out</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hours</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php while ($record = $attendanceRecords->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo date('M d, Y', strtotime($record['date'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : '-'; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '-'; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                        <?php 
                                                        $statusClass = 'text-gray-500';
                                                        if ($record['status'] == 'present') {
                                                            $statusClass = 'text-green-600';
                                                        } elseif ($record['status'] == 'absent') {
                                                            $statusClass = 'text-red-600';
                                                        } elseif ($record['status'] == 'leave') {
                                                            $statusClass = 'text-yellow-600';
                                                        }
                                                        ?>
                                                        <span class="<?php echo $statusClass; ?> font-medium">
                                                            <?php echo ucfirst($record['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php 
                                                        if ($record['check_in'] && $record['check_out']) {
                                                            $checkIn = new DateTime($record['check_in']);
                                                            $checkOut = new DateTime($record['check_out']);
                                                            $interval = $checkIn->diff($checkOut);
                                                            $hours = $interval->h + ($interval->days * 24);
                                                            $minutes = $interval->i;
                                                            echo $hours . 'h ' . $minutes . 'm';
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination -->
                                <?php if ($totalPages > 1): ?>
                                    <div class="mt-6 flex justify-center">
                                        <nav class="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                            <!-- Previous Page Button -->
                                            <?php if ($currentPage > 1): ?>
                                                <a href="?page=<?php echo $currentPage - 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                    <span class="sr-only">Previous</span>
                                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                </a>
                                            <?php else: ?>
                                                <span class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400">
                                                    <span class="sr-only">Previous</span>
                                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                            <?php endif; ?>
                                            
                                            <!-- Page Numbers -->
                                            <?php 
                                            $startPage = max(1, $currentPage - 2);
                                            $endPage = min($totalPages, $currentPage + 2);
                                            
                                            for ($i = $startPage; $i <= $endPage; $i++): 
                                            ?>
                                                <?php if ($i == $currentPage): ?>
                                                    <span class="relative inline-flex items-center px-4 py-2 border border-indigo-500 bg-indigo-50 text-sm font-medium text-indigo-600">
                                                        <?php echo $i; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <a href="?page=<?php echo $i; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                        <?php echo $i; ?>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                            
                                            <!-- Next Page Button -->
                                            <?php if ($currentPage < $totalPages): ?>
                                                <a href="?page=<?php echo $currentPage + 1; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                    <span class="sr-only">Next</span>
                                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                </a>
                                            <?php else: ?>
                                                <span class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-gray-100 text-sm font-medium text-gray-400">
                                                    <span class="sr-only">Next</span>
                                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                            <?php endif; ?>
                                        </nav>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-gray-500 text-center py-4">No attendance records found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
