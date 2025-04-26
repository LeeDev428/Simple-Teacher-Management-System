<?php
session_start();
include '../middleware.php';
adminOnly();
include '../database/db_connection.php';

$successMessage = '';
$errorMessage = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set salary rate for a teacher
    if (isset($_POST['set_salary_rate'])) {
        $teacherId = $_POST['teacher_id'];
        $dailyRate = $_POST['daily_rate'];
        $hourlyRate = $_POST['hourly_rate'];
        $expectedHours = $_POST['expected_hours'];
        
        // Check if a rate already exists for this teacher
        $checkQuery = "SELECT id FROM salary_rates WHERE teacher_id = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->bind_param("i", $teacherId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing rate
            $rateId = $result->fetch_assoc()['id'];
            $updateQuery = "UPDATE salary_rates SET daily_rate = ?, hourly_rate = ?, expected_hours = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("dddi", $dailyRate, $hourlyRate, $expectedHours, $rateId);
            
            if ($stmt->execute()) {
                $successMessage = "Salary rate updated successfully.";
            } else {
                $errorMessage = "Error updating salary rate: " . $conn->error;
            }
        } else {
            // Insert new rate
            $insertQuery = "INSERT INTO salary_rates (teacher_id, daily_rate, hourly_rate, expected_hours) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("iddd", $teacherId, $dailyRate, $hourlyRate, $expectedHours);
            
            if ($stmt->execute()) {
                $successMessage = "Salary rate set successfully.";
            } else {
                $errorMessage = "Error setting salary rate: " . $conn->error;
            }
        }
        $stmt->close();
    }
    
    // Recalculate salary for a date range
    if (isset($_POST['recalculate_salary'])) {
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        
        // Delete existing calculations in the date range
        $deleteQuery = "DELETE FROM salary_calculations WHERE date BETWEEN ? AND ?";
        $stmt = $conn->prepare($deleteQuery);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        
        // Calculate salary for all teachers with attendance in the date range
        $attendanceQuery = "SELECT a.id, a.teacher_id, a.date, a.check_in, a.check_out, 
                          sr.daily_rate, sr.hourly_rate, sr.expected_hours
                          FROM attendance a
                          JOIN salary_rates sr ON a.teacher_id = sr.teacher_id
                          WHERE a.date BETWEEN ? AND ?";
        $stmt = $conn->prepare($attendanceQuery);
        $stmt->bind_param("ss", $startDate, $endDate);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $calculatedCount = 0;
        while ($record = $result->fetch_assoc()) {
            $attendanceId = $record['id'];
            $teacherId = $record['teacher_id'];
            $date = $record['date'];
            $checkIn = $record['check_in'];
            $checkOut = $record['check_out'];
            $dailyRate = $record['daily_rate'];
            $hourlyRate = $record['hourly_rate'];
            $expectedHours = $record['expected_hours'];
            
            // Skip if check-in or check-out is missing
            if (!$checkIn || !$checkOut) {
                continue;
            }
            
            // Calculate hours worked
            $checkInTime = new DateTime($checkIn);
            $checkOutTime = new DateTime($checkOut);
            $expectedStartTime = new DateTime($date . ' 08:00:00'); // Assuming work starts at 8 AM
            
            $hoursWorked = ($checkOutTime->getTimestamp() - $checkInTime->getTimestamp()) / 3600;
            $lateHours = max(0, ($checkInTime->getTimestamp() - $expectedStartTime->getTimestamp()) / 3600);
            
            // Calculate salary
            $expectedAmount = $dailyRate;
            $penaltyAmount = 0;
            
            // Apply penalty: 10% of daily rate per hour late
            if ($lateHours > 0) {
                $penaltyRate = 0.10; // 10% per hour late
                $penaltyAmount = min($dailyRate, $dailyRate * $lateHours * $penaltyRate);
            }
            
            $actualAmount = max(0, $expectedAmount - $penaltyAmount);
            
            // Insert salary calculation
            $insertQuery = "INSERT INTO salary_calculations 
                          (teacher_id, attendance_id, date, expected_amount, actual_amount, 
                           penalty_amount, total_amount, hours_worked, late_hours, status, notes)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)";
            $notes = $lateHours > 0 ? "Late by " . number_format($lateHours, 2) . " hours. Penalty applied." : "On time.";
            
            $calcStmt = $conn->prepare($insertQuery);
            $calcStmt->bind_param("iisddddds", $teacherId, $attendanceId, $date, $expectedAmount, 
                                $actualAmount, $penaltyAmount, $actualAmount, $hoursWorked, $lateHours, $notes);
            
            if ($calcStmt->execute()) {
                $calculatedCount++;
            }
            $calcStmt->close();
        }
        
        if ($calculatedCount > 0) {
            $successMessage = "Salaries recalculated successfully for $calculatedCount attendance records.";
        } else {
            $errorMessage = "No valid attendance records found in the selected date range.";
        }
        $stmt->close();
    }
}

// Get all teachers with their salary rates
$teacherQuery = "SELECT t.id, t.name, t.email, t.subject, t.qualification,
                IFNULL(sr.daily_rate, 0) as daily_rate,
                IFNULL(sr.hourly_rate, 0) as hourly_rate,
                IFNULL(sr.expected_hours, 8) as expected_hours
                FROM teachers t
                LEFT JOIN salary_rates sr ON t.id = sr.teacher_id
                ORDER BY t.name";
$teacherResult = $conn->query($teacherQuery);
$teachers = [];

while ($row = $teacherResult->fetch_assoc()) {
    $teachers[] = $row;
}

// Get salary totals by month
$salaryTotalsQuery = "SELECT 
                    DATE_FORMAT(date, '%Y-%m') as month_year,
                    SUM(total_amount) as total,
                    COUNT(DISTINCT teacher_id) as teacher_count
                    FROM salary_calculations
                    GROUP BY month_year
                    ORDER BY month_year DESC
                    LIMIT 12";
$salaryTotalsResult = $conn->query($salaryTotalsQuery);
$salaryTotals = [];

while ($salaryTotalsResult && $row = $salaryTotalsResult->fetch_assoc()) {
    $salaryTotals[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Salary Management for Teachers">
    <meta name="author" content="School Administration">
    <title>Salary Management | Admin Panel</title>
    
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
                    <h1 class="text-xl font-bold text-indigo-800">Salary Management</h1>
                    <div><!-- Placeholder for alignment --></div>
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

                <!-- Salary Management Tabs -->
                <div class="mb-6">
                    <div class="sm:hidden">
                        <label for="tabs" class="sr-only">Select a tab</label>
                        <select id="tabs" name="tabs" class="block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            <option selected>Teacher Rates</option>
                            <option>Salary Calculations</option>
                            <option>Reports</option>
                        </select>
                    </div>
                    <div class="hidden sm:block">
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex" aria-label="Tabs">
                                <a href="#teacher-rates" class="tab-link active w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm border-indigo-500 text-indigo-600">
                                    Teacher Rates
                                </a>
                                <a href="#salary-calculations" class="tab-link w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                    Salary Calculations
                                </a>
                                <a href="#salary-reports" class="tab-link w-1/3 py-4 px-1 text-center border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                    Reports
                                </a>
                            </nav>
                        </div>
                    </div>
                </div>

                <!-- Teacher Rates Tab -->
                <div id="teacher-rates" class="tab-content active">
                    <div class="bg-white rounded-lg shadow mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="font-bold text-lg text-gray-800">Teacher Salary Rates</h2>
                        </div>
                        <div class="p-6 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qualification</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Rate</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hourly Rate</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($teachers as $teacher): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($teacher['subject'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($teacher['qualification'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            $<?php echo number_format($teacher['daily_rate'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            $<?php echo number_format($teacher['hourly_rate'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="openRateModal(<?php echo $teacher['id']; ?>, '<?php echo addslashes($teacher['name']); ?>', <?php echo $teacher['daily_rate']; ?>, <?php echo $teacher['hourly_rate']; ?>, <?php echo $teacher['expected_hours']; ?>)" class="text-indigo-600 hover:text-indigo-900">
                                                Set Rate
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Salary Calculations Tab -->
                <div id="salary-calculations" class="tab-content hidden">
                    <div class="bg-white rounded-lg shadow mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="font-bold text-lg text-gray-800">Calculate Salaries</h2>
                        </div>
                        <div class="p-6">
                            <form method="post" action="" class="space-y-6">
                                <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                    <div class="sm:col-span-3">
                                        <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                                        <div class="mt-1">
                                            <input type="date" name="start_date" id="start_date" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                        </div>
                                    </div>
                                    <div class="sm:col-span-3">
                                        <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                                        <div class="mt-1">
                                            <input type="date" name="end_date" id="end_date" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <button type="submit" name="recalculate_salary" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Calculate Salaries
                                    </button>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <p>This will recalculate salaries for all teachers with attendance records in the selected date range.</p>
                                    <p class="mt-1">The calculation includes penalties for lateness (10% of daily rate per hour late).</p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Salary Reports Tab -->
                <div id="salary-reports" class="tab-content hidden">
                    <div class="bg-white rounded-lg shadow mb-6">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="font-bold text-lg text-gray-800">Salary Reports by Month</h2>
                        </div>
                        <div class="p-6">
                            <?php if (empty($salaryTotals)): ?>
                                <p class="text-gray-500 text-center py-4">No salary data available yet.</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teachers</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($salaryTotals as $month): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?php 
                                                            $dateObj = DateTime::createFromFormat('Y-m', $month['month_year']);
                                                            echo $dateObj ? $dateObj->format('F Y') : $month['month_year']; 
                                                        ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo $month['teacher_count']; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        $<?php echo number_format($month['total'], 2); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <a href="salary_details.php?month=<?php echo $month['month_year']; ?>" class="text-indigo-600 hover:text-indigo-900">View Details</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Set Rate Modal -->
    <div id="rateModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="post" action="">
                    <input type="hidden" id="modal_teacher_id" name="teacher_id" value="">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                    Set Salary Rate for <span id="modal_teacher_name"></span>
                                </h3>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label for="daily_rate" class="block text-sm font-medium text-gray-700">Daily Rate ($)</label>
                                        <input type="number" step="0.01" name="daily_rate" id="modal_daily_rate" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required min="0">
                                    </div>
                                    <div>
                                        <label for="hourly_rate" class="block text-sm font-medium text-gray-700">Hourly Rate ($)</label>
                                        <input type="number" step="0.01" name="hourly_rate" id="modal_hourly_rate" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required min="0">
                                    </div>
                                    <div>
                                        <label for="expected_hours" class="block text-sm font-medium text-gray-700">Expected Hours Per Day</label>
                                        <input type="number" step="0.5" name="expected_hours" id="modal_expected_hours" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required min="1" max="24">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="set_salary_rate" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Save Rate
                        </button>
                        <button type="button" onclick="closeRateModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
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
            
            // Handle tab switching
            const tabLinks = document.querySelectorAll('.tab-link');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabLinks.forEach(tabLink => {
                tabLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Remove active class from all tabs
                    tabLinks.forEach(link => {
                        link.classList.remove('active', 'border-indigo-500', 'text-indigo-600');
                        link.classList.add('border-transparent', 'text-gray-500');
                    });
                    
                    // Add active class to clicked tab
                    this.classList.add('active', 'border-indigo-500', 'text-indigo-600');
                    this.classList.remove('border-transparent', 'text-gray-500');
                    
                    // Hide all tab contents
                    tabContents.forEach(content => {
                        content.classList.add('hidden');
                    });
                    
                    // Show the corresponding tab content
                    const targetId = this.getAttribute('href').substring(1);
                    document.getElementById(targetId).classList.remove('hidden');
                });
            });
        });

        // Modal functions
        function openRateModal(teacherId, teacherName, dailyRate, hourlyRate, expectedHours) {
            document.getElementById('modal_teacher_id').value = teacherId;
            document.getElementById('modal_teacher_name').textContent = teacherName;
            document.getElementById('modal_daily_rate').value = dailyRate;
            document.getElementById('modal_hourly_rate').value = hourlyRate;
            document.getElementById('modal_expected_hours').value = expectedHours;
            document.getElementById('rateModal').classList.remove('hidden');
        }

        function closeRateModal() {
            document.getElementById('rateModal').classList.add('hidden');
        }
    </script>
</body>
</html>
