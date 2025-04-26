<?php
session_start();
include '../middleware.php';
adminOnly();
include '../database/db_connection.php';

// Get all teachers
$teacherQuery = "SELECT id, name FROM teachers ORDER BY name";
$teacherResult = $conn->query($teacherQuery);
$teachers = [];
while ($row = $teacherResult->fetch_assoc()) {
    $teachers[] = $row;
}

// Default to current month if not specified
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$selectedTeacherId = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : (count($teachers) > 0 ? $teachers[0]['id'] : 0);

// Get attendance data for the selected teacher and month
$attendanceData = [];
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

if ($selectedTeacherId > 0) {
    $firstDayOfMonth = sprintf("%04d-%02d-01", $year, $month);
    $lastDayOfMonth = sprintf("%04d-%02d-%02d", $year, $month, $daysInMonth);
    
    $attendanceQuery = "SELECT date, check_in, check_out, status, notes 
                      FROM attendance 
                      WHERE teacher_id = ? AND date BETWEEN ? AND ?
                      ORDER BY date";
    $stmt = $conn->prepare($attendanceQuery);
    $stmt->bind_param("iss", $selectedTeacherId, $firstDayOfMonth, $lastDayOfMonth);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $day = intval(date('j', strtotime($row['date'])));
        $attendanceData[$day] = $row;
    }
    $stmt->close();
}

// Month and year navigation data
$monthNames = [
    1 => 'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

// Previous month
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

// Next month
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Teacher Attendance Monitoring">
    <meta name="author" content="School Administration">
    <meta name="theme-color" content="#4F46E5">
    <title>Monitor Attendance | Admin Panel</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
    
    <style>
        .attendance-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
        }
        
        .day-cell {
            aspect-ratio: 1/1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            border: 1px solid #e5e7eb;
            border-radius: 0.375rem;
            position: relative;
        }
        
        .day-number {
            position: absolute;
            top: 4px;
            left: 4px;
            font-size: 0.75rem;
            color: #6b7280;
        }
    </style>
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
                    <h1 class="text-xl font-bold text-indigo-800">Attendance Monitoring</h1>
                    <div><!-- Placeholder for alignment --></div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="p-6">
                <!-- Filters and Controls -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="font-bold text-lg text-gray-800">Attendance Calendar</h2>
                    </div>
                    <div class="p-6">
                        <form action="" method="get" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div>
                                <label for="teacher_id" class="block text-sm font-medium text-gray-700 mb-1">Select Teacher</label>
                                <select id="teacher_id" name="teacher_id" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher['id'] == $selectedTeacherId ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">Month</label>
                                <select id="month" name="month" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <?php foreach ($monthNames as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo $num == $month ? 'selected' : ''; ?>>
                                            <?php echo $name; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="year" class="block text-sm font-medium text-gray-700 mb-1">Year</label>
                                <select id="year" name="year" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <?php for ($i = date('Y') - 2; $i <= date('Y') + 2; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $i == $year ? 'selected' : ''; ?>>
                                            <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="col-span-3">
                                <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    View Attendance
                                </button>
                            </div>
                        </form>
                        
                        <!-- Month Calendar -->
                        <div class="mb-4">
                            <div class="flex justify-between items-center mb-4">
                                <a href="?teacher_id=<?php echo $selectedTeacherId; ?>&year=<?php echo $prevYear; ?>&month=<?php echo $prevMonth; ?>" class="text-indigo-600 hover:text-indigo-800">
                                    &larr; <?php echo $monthNames[$prevMonth]; ?>
                                </a>
                                <h3 class="text-xl font-semibold text-gray-800"><?php echo $monthNames[$month] . ' ' . $year; ?></h3>
                                <a href="?teacher_id=<?php echo $selectedTeacherId; ?>&year=<?php echo $nextYear; ?>&month=<?php echo $nextMonth; ?>" class="text-indigo-600 hover:text-indigo-800">
                                    <?php echo $monthNames[$nextMonth]; ?> &rarr;
                                </a>
                            </div>
                            
                            <div class="attendance-grid mb-6">
                                <!-- Days of the week headers -->
                                <div class="text-center font-semibold text-gray-600 py-2">Sun</div>
                                <div class="text-center font-semibold text-gray-600 py-2">Mon</div>
                                <div class="text-center font-semibold text-gray-600 py-2">Tue</div>
                                <div class="text-center font-semibold text-gray-600 py-2">Wed</div>
                                <div class="text-center font-semibold text-gray-600 py-2">Thu</div>
                                <div class="text-center font-semibold text-gray-600 py-2">Fri</div>
                                <div class="text-center font-semibold text-gray-600 py-2">Sat</div>
                                
                                <!-- Empty cells for days before the first day of the month -->
                                <?php 
                                $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year); 
                                $dayOfWeek = date('w', $firstDayOfMonth);
                                for ($i = 0; $i < $dayOfWeek; $i++): 
                                ?>
                                    <div class="day-cell bg-gray-50"></div>
                                <?php endfor; ?>
                                
                                <!-- Calendar days -->
                                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                                    <?php 
                                    $dayClass = "day-cell bg-white";
                                    $icon = "";
                                    $statusClass = "";
                                    $tooltip = "";
                                    
                                    if (isset($attendanceData[$day])) {
                                        $record = $attendanceData[$day];
                                        
                                        if ($record['check_in'] && $record['check_out']) {
                                            $dayClass = "day-cell bg-green-50";
                                            $icon = "✓";
                                            $statusClass = "text-green-600";
                                            $tooltip = "Present: " . date('h:i A', strtotime($record['check_in'])) . " - " . date('h:i A', strtotime($record['check_out']));
                                        } elseif ($record['check_in'] && !$record['check_out']) {
                                            $dayClass = "day-cell bg-yellow-50";
                                            $icon = "?";
                                            $statusClass = "text-yellow-600";
                                            $tooltip = "Checked in at " . date('h:i A', strtotime($record['check_in'])) . " but no checkout";
                                        } else {
                                            $dayClass = "day-cell bg-red-50";
                                            $icon = "✗";
                                            $statusClass = "text-red-600";
                                            $tooltip = "Absent";
                                        }
                                    } else {
                                        // Check if it's a past date
                                        $currentDate = mktime(0, 0, 0, $month, $day, $year);
                                        if ($currentDate < strtotime('today') && date('N', $currentDate) < 6) { // Weekday in the past
                                            $dayClass = "day-cell bg-red-50";
                                            $icon = "✗";
                                            $statusClass = "text-red-600";
                                            $tooltip = "Absent";
                                        }
                                    }
                                    ?>
                                    <div class="<?php echo $dayClass; ?>" title="<?php echo $tooltip; ?>">
                                        <span class="day-number"><?php echo $day; ?></span>
                                        <?php if ($icon): ?>
                                            <span class="<?php echo $statusClass; ?> text-xl"><?php echo $icon; ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            
                            <!-- Legend -->
                            <div class="flex flex-wrap gap-4 justify-center text-sm text-gray-600">
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-green-50 border border-green-200 rounded mr-1"></div>
                                    <span>Present</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-yellow-50 border border-yellow-200 rounded mr-1"></div>
                                    <span>Incomplete</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-4 h-4 bg-red-50 border border-red-200 rounded mr-1"></div>
                                    <span>Absent</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Details -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="font-bold text-lg text-gray-800">Attendance Details for <?php echo $monthNames[$month] . ' ' . $year; ?></h2>
                    </div>
                    <div class="p-6">
                        <?php if (empty($attendanceData)): ?>
                            <p class="text-gray-500 text-center py-4">No attendance records found for this month.</p>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check In</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check Out</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($attendanceData as $day => $record): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?php echo date('F d, Y (l)', strtotime($record['date'])); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : '-'; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '-'; ?>
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
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <?php 
                                                    $statusClass = 'text-gray-500';
                                                    $statusText = 'Unknown';
                                                    
                                                    if ($record['check_in'] && $record['check_out']) {
                                                        $statusClass = 'text-green-600';
                                                        $statusText = 'Present';
                                                    } elseif ($record['check_in'] && !$record['check_out']) {
                                                        $statusClass = 'text-yellow-600';
                                                        $statusText = 'Incomplete';
                                                    } elseif ($record['status'] == 'absent') {
                                                        $statusClass = 'text-red-600';
                                                        $statusText = 'Absent';
                                                    } elseif ($record['status'] == 'leave') {
                                                        $statusClass = 'text-blue-600';
                                                        $statusText = 'Leave';
                                                    }
                                                    ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-opacity-10 <?php echo $statusClass; ?> bg-<?php echo explode('-', $statusClass)[1]; ?>-100">
                                                        <?php echo $statusText; ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?php echo $record['notes'] ? htmlspecialchars($record['notes'], ENT_QUOTES, 'UTF-8') : '-'; ?>
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
