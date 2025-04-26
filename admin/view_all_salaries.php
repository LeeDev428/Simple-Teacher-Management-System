<?php
session_start();
include '../middleware.php';
adminOnly();
include '../database/db_connection.php';

// Get the selected month and year or default to current month
$currentMonth = date('m');
$currentYear = date('Y');
$selectedMonth = isset($_GET['month']) ? intval($_GET['month']) : intval($currentMonth);
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : intval($currentYear);

// Month names for dropdown
$monthNames = [
    1 => 'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

// Get all teachers with their salaries and attendance data
$query = "SELECT t.id, t.name, t.email, t.subject, t.grade, 
          sg.daily_rate, 
          COUNT(a.id) as attendance_count,
          SUM(CASE WHEN a.check_in IS NOT NULL AND a.check_out IS NOT NULL THEN 1 ELSE 0 END) as days_worked,
          SUM(CASE 
              WHEN TIME(a.check_in) > '08:00:00' THEN 
                  TIMESTAMPDIFF(MINUTE, CONCAT(DATE(a.check_in), ' 08:00:00'), a.check_in) / 60
              ELSE 0 
          END) as total_late_hours
          FROM teachers t
          LEFT JOIN salary_grade sg ON t.grade = sg.grade
          LEFT JOIN attendance a ON t.id = a.teacher_id AND 
               MONTH(a.date) = ? AND YEAR(a.date) = ?
          GROUP BY t.id
          ORDER BY t.name";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $selectedMonth, $selectedYear);
$stmt->execute();
$result = $stmt->get_result();
$teachers = [];

while ($row = $result->fetch_assoc()) {
    // Calculate total working days in the selected month (excluding weekends)
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
    $workingDays = 0;
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = sprintf("%04d-%02d-%02d", $selectedYear, $selectedMonth, $day);
        $dayOfWeek = date('N', strtotime($date)); // 1 (Monday) to 7 (Sunday)
        if ($dayOfWeek < 6) { // Monday to Friday
            $workingDays++;
        }
    }

    // Calculate salary with deductions for lateness
    $dailyRate = $row['daily_rate'] ?? 0;
    $daysWorked = $row['days_worked'] ?? 0;
    $lateHours = $row['total_late_hours'] ?? 0;
    
    // Apply 10% penalty per late hour
    $latePenaltyRate = 0.10; // 10% per hour
    $latePenalty = $lateHours * $latePenaltyRate * $dailyRate;
    
    // Calculate gross and net salary
    $grossSalary = $daysWorked * $dailyRate;
    $netSalary = max(0, $grossSalary - $latePenalty);
    
    $row['working_days'] = $workingDays;
    $row['days_worked'] = $daysWorked;
    $row['late_hours'] = $lateHours;
    $row['late_penalty'] = $latePenalty;
    $row['gross_salary'] = $grossSalary;
    $row['net_salary'] = $netSalary;
    
    $teachers[] = $row;
}
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Teacher Salaries Overview">
    <meta name="author" content="School Administration">
    <meta name="theme-color" content="#4F46E5">
    <title>Teacher Salaries | Admin Panel</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                width: 100%;
                height: 100%;
                margin: 0;
                padding: 0;
                font-size: 12pt;
            }
            .payslip-container {
                page-break-after: always;
                break-after: page;
            }
        }
        .print-only {
            display: none;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="lg:block no-print">
            <?php include 'sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-64">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm no-print">
                <div class="flex items-center justify-between p-4">
                    <button id="openSidebar" class="lg:hidden text-indigo-600">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <h1 class="text-xl font-bold text-indigo-800">Teacher Salaries Overview</h1>
                    <div><!-- Placeholder for alignment --></div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-6">
                <!-- Month Selection -->
                <div class="bg-white rounded-lg shadow mb-6 no-print">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="font-bold text-lg text-gray-800">Select Month</h2>
                    </div>
                    <div class="p-6">
                        <form action="" method="get" class="flex flex-wrap gap-4 items-end">
                            <div>
                                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                                <select id="month" name="month" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <?php foreach ($monthNames as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo $num == $selectedMonth ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                                <select id="year" name="year" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <?php for ($i = date('Y') - 2; $i <= date('Y'); $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i == $selectedYear ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    View Salaries
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Salaries Table -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                        <h2 class="font-bold text-lg text-gray-800">Teacher Salaries for <?php echo $monthNames[$selectedMonth] . ' ' . $selectedYear; ?></h2>
                        <button id="printAllPayslips" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 no-print">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Print All Payslips
                        </button>
                    </div>
                    <div class="p-6 overflow-x-auto">
                        <?php if (empty($teachers)): ?>
                            <p class="text-gray-500 text-center py-4">No teacher data found for the selected month.</p>
                        <?php else: ?>
                            <table class="min-w-full divide-y divide-gray-200 no-print">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Rate</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days Worked</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Late Hours</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Salary</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Deductions</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Net Salary</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($teachers as $teacher): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                Grade <?php echo $teacher['grade']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                PHP <?php echo number_format($teacher['daily_rate'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $teacher['days_worked']; ?> / <?php echo $teacher['working_days']; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo number_format($teacher['late_hours'], 2); ?> hrs
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                PHP <?php echo number_format($teacher['gross_salary'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                PHP <?php echo number_format($teacher['late_penalty'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                PHP <?php echo number_format($teacher['net_salary'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button class="print-payslip text-indigo-600 hover:text-indigo-900" 
                                                        data-teacher-id="<?php echo $teacher['id']; ?>"
                                                        data-teacher-name="<?php echo htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-teacher-email="<?php echo htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-teacher-subject="<?php echo htmlspecialchars($teacher['subject'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-teacher-grade="<?php echo $teacher['grade']; ?>"
                                                        data-daily-rate="<?php echo $teacher['daily_rate']; ?>"
                                                        data-days-worked="<?php echo $teacher['days_worked']; ?>"
                                                        data-working-days="<?php echo $teacher['working_days']; ?>"
                                                        data-late-hours="<?php echo $teacher['late_hours']; ?>"
                                                        data-gross-salary="<?php echo $teacher['gross_salary']; ?>"
                                                        data-late-penalty="<?php echo $teacher['late_penalty']; ?>"
                                                        data-net-salary="<?php echo $teacher['net_salary']; ?>"
                                                        data-month="<?php echo $selectedMonth; ?>"
                                                        data-year="<?php echo $selectedYear; ?>">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                                    </svg>
                                                    Print Payslip
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        
                        <!-- Printable Payslips Section -->
                        <div id="payslipContainer" class="print-only">
                            <!-- Payslips will be generated here when printing -->
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Payslip Template (Hidden) -->
    <template id="payslipTemplate">
        <div class="payslip-container bg-white p-8 mb-8 max-w-4xl mx-auto">
            <div class="border-b border-gray-200 pb-4 mb-6">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-800">SCHOOL MANAGEMENT SYSTEM</h1>
                    <div class="text-right">
                        <h2 class="text-xl font-bold text-gray-800">PAYSLIP</h2>
                        <p class="text-gray-600">For the month of <span class="payslip-month"></span></p>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-6 mb-6">
                <div>
                    <h3 class="text-gray-600 font-semibold mb-2">Employee Information</h3>
                    <p class="mb-1"><span class="font-semibold">Name:</span> <span class="payslip-name"></span></p>
                    <p class="mb-1"><span class="font-semibold">Email:</span> <span class="payslip-email"></span></p>
                    <p class="mb-1"><span class="font-semibold">Subject:</span> <span class="payslip-subject"></span></p>
                    <p class="mb-1"><span class="font-semibold">Grade Level:</span> <span class="payslip-grade"></span></p>
                </div>
                <div>
                    <h3 class="text-gray-600 font-semibold mb-2">Salary Information</h3>
                    <p class="mb-1"><span class="font-semibold">Daily Rate:</span> PHP <span class="payslip-daily-rate"></span></p>
                    <p class="mb-1"><span class="font-semibold">Days Worked:</span> <span class="payslip-days-worked"></span> of <span class="payslip-working-days"></span> working days</p>
                    <p class="mb-1"><span class="font-semibold">Late Hours:</span> <span class="payslip-late-hours"></span> hours</p>
                </div>
            </div>
            
            <div class="mb-6">
                <h3 class="text-gray-600 font-semibold mb-2">Earnings & Deductions</h3>
                <table class="w-full">
                    <tr class="border-b border-gray-200">
                        <td class="py-2">Gross Salary (Daily Rate × Days Worked)</td>
                        <td class="py-2 text-right">PHP <span class="payslip-gross"></span></td>
                    </tr>
                    <tr class="border-b border-gray-200">
                        <td class="py-2">Late Penalty (10% of Daily Rate × Late Hours)</td>
                        <td class="py-2 text-right">- PHP <span class="payslip-penalty"></span></td>
                    </tr>
                    <tr class="border-t-2 border-gray-300 font-bold">
                        <td class="py-2">NET SALARY</td>
                        <td class="py-2 text-right">PHP <span class="payslip-net"></span></td>
                    </tr>
                </table>
            </div>
            
            <div class="mt-12 pt-8 border-t border-gray-200">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <p class="font-semibold">Employee Signature</p>
                        <div class="mt-8 border-b border-gray-400 w-48"></div>
                    </div>
                    <div>
                        <p class="font-semibold">Authorized Signature</p>
                        <div class="mt-8 border-b border-gray-400 w-48"></div>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 text-center text-xs text-gray-500">
                <p>This is a computer-generated document. No signature required.</p>
                <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
            </div>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle
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
            
            // Print single payslip
            const printButtons = document.querySelectorAll('.print-payslip');
            printButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Get teacher data from data attributes
                    const data = this.dataset;
                    generatePayslip(data);
                    
                    // Print the payslip
                    window.print();
                });
            });
            
            // Print all payslips
            const printAllButton = document.getElementById('printAllPayslips');
            if (printAllButton) {
                printAllButton.addEventListener('click', function() {
                    const payslipContainer = document.getElementById('payslipContainer');
                    payslipContainer.innerHTML = '';
                    
                    // Generate all payslips
                    printButtons.forEach(button => {
                        generatePayslip(button.dataset, true);
                    });
                    
                    // Print all payslips
                    window.print();
                });
            }
            
            // Function to generate payslip from data
            function generatePayslip(data, appendToContainer = false) {
                const template = document.getElementById('payslipTemplate');
                const payslipContainer = document.getElementById('payslipContainer');
                
                // Clear container if not appending
                if (!appendToContainer) {
                    payslipContainer.innerHTML = '';
                }
                
                // Clone the template
                const clone = template.content.cloneNode(true);
                
                // Fill in the payslip data
                clone.querySelector('.payslip-month').textContent = getMonthName(data.month) + ' ' + data.year;
                clone.querySelector('.payslip-name').textContent = data.teacherName;
                clone.querySelector('.payslip-email').textContent = data.teacherEmail;
                clone.querySelector('.payslip-subject').textContent = data.teacherSubject;
                clone.querySelector('.payslip-grade').textContent = data.teacherGrade;
                clone.querySelector('.payslip-daily-rate').textContent = formatNumber(data.dailyRate);
                clone.querySelector('.payslip-days-worked').textContent = data.daysWorked;
                clone.querySelector('.payslip-working-days').textContent = data.workingDays;
                clone.querySelector('.payslip-late-hours').textContent = formatNumber(data.lateHours);
                clone.querySelector('.payslip-gross').textContent = formatNumber(data.grossSalary);
                clone.querySelector('.payslip-penalty').textContent = formatNumber(data.latePenalty);
                clone.querySelector('.payslip-net').textContent = formatNumber(data.netSalary);
                
                // Add to container
                payslipContainer.appendChild(clone);
            }
            
            // Helper function to get month name
            function getMonthName(monthNum) {
                const months = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];
                return months[parseInt(monthNum) - 1];
            }
            
            // Helper function to format number with commas and 2 decimal places
            function formatNumber(num) {
                return parseFloat(num).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        });
    </script>
</body>
</html>
