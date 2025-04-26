<?php
session_start();
include '../middleware.php';
userOnly();
include '../database/db_connection.php';

$userId = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

// Get teacher information including grade and salary rate
$query = "SELECT t.id, t.name, t.grade, sg.daily_rate
          FROM teachers t
          LEFT JOIN salary_grade sg ON t.grade = sg.grade
          WHERE t.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $errorMessage = "Teacher profile not found.";
    $teacherId = null;
    $teacherInfo = null;
} else {
    $teacherInfo = $result->fetch_assoc();
    $teacherId = $teacherInfo['id'];
}
$stmt->close();

// Get current month and year
$currentMonth = date('m');
$currentYear = date('Y');
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval($currentMonth);
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : intval($currentYear);

// Get attendance records for the selected month
$attendanceRecords = [];
$totalSalary = 0;
$workDays = 0;
$lateHours = 0;
$lateDeductions = 0;

if ($teacherId) {
    $monthStart = sprintf("%04d-%02d-01", $selectedYear, $selectedMonth);
    $monthEnd = date('Y-m-t', strtotime($monthStart));
    
    $query = "SELECT date, check_in, check_out, status, notes 
              FROM attendance 
              WHERE teacher_id = ? AND date BETWEEN ? AND ?
              ORDER BY date";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $teacherId, $monthStart, $monthEnd);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $dailyRate = $teacherInfo['daily_rate'] ?? 0;
        $dailySalary = $dailyRate;
        $hoursWorked = 0;
        $lateHoursToday = 0;
        $deduction = 0;
        
        // Calculate hours worked and lateness
        if ($row['check_in'] && $row['check_out']) {
            $checkInTime = new DateTime($row['check_in']);
            $checkOutTime = new DateTime($row['check_out']);
            $expectedStartTime = new DateTime($row['date'] . ' 08:00:00'); // Assuming work starts at 8 AM
            
            // Calculate hours worked
            $interval = $checkInTime->diff($checkOutTime);
            $hoursWorked = $interval->h + ($interval->i / 60) + ($interval->days * 24);
            
            // Calculate late hours
            if ($checkInTime > $expectedStartTime) {
                $lateInterval = $expectedStartTime->diff($checkInTime);
                $lateHoursToday = $lateInterval->h + ($lateInterval->i / 60);
                
                // Apply 10% penalty per hour late
                if ($lateHoursToday > 0) {
                    $penaltyRate = 0.10; // 10% per hour
                    $deduction = min($dailyRate, $dailyRate * $lateHoursToday * $penaltyRate);
                    $dailySalary = $dailyRate - $deduction;
                }
                
                $lateHours += $lateHoursToday;
                $lateDeductions += $deduction;
            }
            
            $workDays++;
            $totalSalary += $dailySalary;
        }
        
        $row['hours_worked'] = $hoursWorked;
        $row['late_hours'] = $lateHoursToday;
        $row['deduction'] = $deduction;
        $row['daily_salary'] = $dailySalary;
        $attendanceRecords[] = $row;
    }
    $stmt->close();
}

// Month names for dropdown
$monthNames = [
    1 => 'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Teacher Salary Information">
    <meta name="author" content="School Administration">
    <meta name="theme-color" content="#4F46E5">
    <title>My Salary | School Management System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
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
                    <h1 class="text-xl font-bold text-indigo-800">My Salary Information</h1>
                    <div><!-- Placeholder for alignment --></div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-6">
                <?php if ($errorMessage): ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($errorMessage == "Teacher profile not found."): ?>
                                        <a href="teacher_profile.php" class="font-medium underline">Create your profile first</a>.
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Salary Information -->
                    <div class="bg-white rounded-lg shadow mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="font-bold text-lg text-gray-800">Salary Overview</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="bg-indigo-50 p-4 rounded-lg">
                                    <div class="text-sm font-medium text-indigo-600">Salary Grade</div>
                                    <div class="mt-1 text-2xl font-semibold">Grade <?php echo $teacherInfo['grade']; ?></div>
                                </div>
                                
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <div class="text-sm font-medium text-green-600">Daily Rate</div>
                                    <div class="mt-1 text-2xl font-semibold">PHP <?php echo number_format($teacherInfo['daily_rate'] ?? 0, 2); ?></div>
                                </div>
                                
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <div class="text-sm font-medium text-blue-600">This Month's Salary</div>
                                    <div class="mt-1 text-2xl font-semibold">PHP <?php echo number_format($totalSalary, 2); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Month Selection and Summary -->
                    <div class="bg-white rounded-lg shadow mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <div class="flex justify-between items-center">
                                <h2 class="font-bold text-lg text-gray-800">Monthly Details</h2>
                                <form action="" method="get" class="flex items-center space-x-2">
                                    <select name="month" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <?php foreach ($monthNames as $num => $name): ?>
                                            <option value="<?php echo $num; ?>" <?php echo $num == $selectedMonth ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="year" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <?php for ($i = date('Y') - 2; $i <= date('Y'); $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $i == $selectedYear ? 'selected' : ''; ?>>
                                                <?php echo $i; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                    <button type="submit" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        View
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="text-sm font-medium text-gray-600">Work Days</div>
                                    <div class="mt-1 text-xl font-semibold"><?php echo $workDays; ?> days</div>
                                </div>
                                
                                <div class="bg-yellow-50 p-4 rounded-lg">
                                    <div class="text-sm font-medium text-yellow-600">Late Hours</div>
                                    <div class="mt-1 text-xl font-semibold"><?php echo number_format($lateHours, 2); ?> hours</div>
                                </div>
                                
                                <div class="bg-red-50 p-4 rounded-lg">
                                    <div class="text-sm font-medium text-red-600">Late Deductions</div>
                                    <div class="mt-1 text-xl font-semibold">PHP <?php echo number_format($lateDeductions, 2); ?></div>
                                </div>
                                
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <div class="text-sm font-medium text-green-600">Total Salary</div>
                                    <div class="mt-1 text-xl font-semibold">PHP <?php echo number_format($totalSalary, 2); ?></div>
                                </div>
                            </div>
                            
                            <!-- Salary Breakdown Table -->
                            <?php if (empty($attendanceRecords)): ?>
                                <p class="text-gray-500 text-center py-4">No attendance records found for this month.</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check In</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check Out</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hours Worked</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Late Hours</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deduction</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Salary</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($attendanceRecords as $record): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php echo date('M d, Y (D)', strtotime($record['date'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : '-'; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '-'; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo number_format($record['hours_worked'], 2); ?> hrs
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php if ($record['late_hours'] > 0): ?>
                                                            <span class="text-red-600"><?php echo number_format($record['late_hours'], 2); ?> hrs</span>
                                                        <?php else: ?>
                                                            <span class="text-green-600">On time</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php if ($record['deduction'] > 0): ?>
                                                            <span class="text-red-600">-PHP <?php echo number_format($record['deduction'], 2); ?></span>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">
                                                        PHP <?php echo number_format($record['daily_salary'], 2); ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Salary Policy Information -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="font-bold text-lg text-gray-800">Salary Policy</h2>
                        </div>
                        <div class="p-6">
                            <div class="prose max-w-none">
                                <p>Your salary is calculated based on the following rules:</p>
                                <ul class="list-disc pl-5 space-y-2 mt-2">
                                    <li>Base salary is determined by your assigned salary grade (Grade <?php echo $teacherInfo['grade']; ?>), which is PHP <?php echo number_format($teacherInfo['daily_rate'] ?? 0, 2); ?> per day.</li>
                                    <li>For every hour late, a 10% deduction is applied to your daily rate.</li>
                                    <li>Total monthly salary is calculated by adding up the adjusted daily rates for days you've worked.</li>
                                    <li>Work days without both check-in and check-out are not counted toward your salary.</li>
                                </ul>
                                <p class="mt-4 text-sm text-gray-500">For questions about your salary, please contact the HR department.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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
