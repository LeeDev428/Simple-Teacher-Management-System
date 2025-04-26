<?php
session_start();
include '../middleware.php';
adminOnly();
include '../database/db_connection.php';

$successMessage = '';
$errorMessage = '';

// Handle salary grade update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_grade'])) {
    $gradeId = isset($_POST['grade_id']) ? $_POST['grade_id'] : 0;
    $gradeLevel = $_POST['grade_level'];
    $dailyRate = $_POST['daily_rate'];
    
    if ($gradeId > 0) {
        // Update existing grade
        $updateQuery = "UPDATE salary_grade SET grade = ?, daily_rate = ? WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("idi", $gradeLevel, $dailyRate, $gradeId);
    } else {
        // Insert new grade
        $updateQuery = "INSERT INTO salary_grade (grade, daily_rate) VALUES (?, ?)";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("id", $gradeLevel, $dailyRate);
    }
    
    if ($stmt->execute()) {
        $successMessage = "Salary grade updated successfully.";
    } else {
        $errorMessage = "Error updating salary grade: " . $conn->error;
    }
    $stmt->close();
}

// Handle teacher grade assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_teacher_grade'])) {
    $teacherId = $_POST['teacher_id'];
    $gradeLevel = $_POST['grade_level'];
    
    $updateQuery = "UPDATE teachers SET grade = ? WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ii", $gradeLevel, $teacherId);
    
    if ($stmt->execute()) {
        $successMessage = "Teacher grade assigned successfully.";
    } else {
        $errorMessage = "Error assigning teacher grade: " . $conn->error;
    }
    $stmt->close();
}

// Get all salary grades
$gradesQuery = "SELECT * FROM salary_grade ORDER BY grade";
$gradesResult = $conn->query($gradesQuery);
$salaryGrades = [];

if ($gradesResult) {
    while ($row = $gradesResult->fetch_assoc()) {
        $salaryGrades[] = $row;
    }
}

// Check if the grade column exists
$checkGradeColumn = "SHOW COLUMNS FROM teachers LIKE 'grade'";
$gradeColumnResult = $conn->query($checkGradeColumn);
$gradeColumnExists = $gradeColumnResult && $gradeColumnResult->num_rows > 0;

// If the grade column doesn't exist, try to create it
if (!$gradeColumnExists) {
    $alterQuery = "ALTER TABLE teachers ADD COLUMN grade INT DEFAULT 1 AFTER qualification";
    $conn->query($alterQuery);
    // Check again if column was successfully added
    $gradeColumnResult = $conn->query($checkGradeColumn);
    $gradeColumnExists = $gradeColumnResult && $gradeColumnResult->num_rows > 0;
}

// Get all teachers with their salaries, safely handling missing columns
if ($gradeColumnExists) {
    // Grade column exists, include it in the query
    $query = "SELECT t.id, t.name, t.email, t.subject, t.qualification, 
              t.grade, IFNULL(sg.daily_rate, 0) as salary, t.joining_date
              FROM teachers t
              LEFT JOIN salary_grade sg ON t.grade = sg.grade
              ORDER BY t.name";
} else {
    // Grade column doesn't exist, use a fallback query
    $query = "SELECT t.id, t.name, t.email, t.subject, t.qualification, 
              1 as grade, IFNULL(sg.daily_rate, 0) as salary, t.joining_date
              FROM teachers t
              LEFT JOIN salary_grade sg ON sg.grade = 1
              ORDER BY t.name";
}
          
$result = $conn->query($query);
$teachers = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Teacher Salary Management">
    <meta name="author" content="School Administration">
    <meta name="theme-color" content="#4F46E5">
    <title>Salary Grade Management | Admin Panel</title>
    
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
                    <h1 class="text-xl font-bold text-indigo-800">Salary Grade Management</h1>
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

                <!-- Salary Grade Management -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="font-bold text-lg text-gray-800">Salary Grades</h2>
                    </div>
                    <div class="p-6">
                        <form method="post" action="" class="mb-6">
                            <input type="hidden" name="grade_id" id="edit_grade_id" value="0">
                            <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                <div class="sm:col-span-2">
                                    <label for="grade_level" class="block text-sm font-medium text-gray-700">Grade Level (1-5)</label>
                                    <div class="mt-1">
                                        <select name="grade_level" id="edit_grade_level" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                            <option value="1">Grade 1</option>
                                            <option value="2">Grade 2</option>
                                            <option value="3">Grade 3</option>
                                            <option value="4">Grade 4</option>
                                            <option value="5">Grade 5</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="daily_rate" class="block text-sm font-medium text-gray-700">Daily Rate (PHP)</label>
                                    <div class="mt-1">
                                        <input type="number" step="0.01" name="daily_rate" id="edit_daily_rate" required class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                </div>
                                <div class="sm:col-span-2 flex items-end">
                                    <button type="submit" name="update_grade" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        Save Grade
                                    </button>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Salary Grades Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Grade Level</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Rate (PHP)</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($salaryGrades)): ?>
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">
                                                No salary grades defined yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($salaryGrades as $grade): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">Grade <?php echo $grade['grade']; ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">PHP <?php echo number_format($grade['daily_rate'], 2); ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button type="button" 
                                                            onclick="editGrade(<?php echo $grade['id']; ?>, <?php echo $grade['grade']; ?>, <?php echo $grade['daily_rate']; ?>)" 
                                                            class="text-indigo-600 hover:text-indigo-900">
                                                        Edit
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Teacher Grade Assignment -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="font-bold text-lg text-gray-800">Teacher Grade Assignment</h2>
                    </div>
                    <div class="p-6 overflow-x-auto">
                        <?php if (empty($teachers)): ?>
                            <p class="text-gray-500 text-center py-4">No teachers found.</p>
                        <?php else: ?>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Qualification</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Current Grade</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Rate</th>
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
                                                <?php echo htmlspecialchars($teacher['subject'], ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo htmlspecialchars($teacher['qualification'], ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                Grade <?php echo $teacher['grade'] ?? 1; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                PHP <?php echo number_format($teacher['salary'] ?? 0, 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button type="button" 
                                                        onclick="openTeacherGradeModal(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo $teacher['grade'] ?? 1; ?>)" 
                                                        class="text-indigo-600 hover:text-indigo-900">
                                                    Assign Grade
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Teacher Grade Modal -->
    <div id="teacherGradeModal" class="fixed inset-0 z-50 overflow-y-auto hidden">
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
                                    Assign Grade for <span id="modal_teacher_name"></span>
                                </h3>
                                <div class="mt-4">
                                    <label for="grade_level" class="block text-sm font-medium text-gray-700">Select Grade Level</label>
                                    <select name="grade_level" id="modal_grade_level" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="1">Grade 1</option>
                                        <option value="2">Grade 2</option>
                                        <option value="3">Grade 3</option>
                                        <option value="4">Grade 4</option>
                                        <option value="5">Grade 5</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="assign_teacher_grade" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Assign Grade
                        </button>
                        <button type="button" onclick="closeTeacherGradeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
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
        });

        // Grade editing function
        function editGrade(gradeId, gradeLevel, dailyRate) {
            document.getElementById('edit_grade_id').value = gradeId;
            document.getElementById('edit_grade_level').value = gradeLevel;
            document.getElementById('edit_daily_rate').value = dailyRate;
            
            // Scroll to the form
            document.querySelector('form').scrollIntoView({ behavior: 'smooth' });
        }

        // Teacher grade modal functions
        function openTeacherGradeModal(teacherId, teacherName, currentGrade) {
            document.getElementById('modal_teacher_id').value = teacherId;
            document.getElementById('modal_teacher_name').textContent = teacherName;
            document.getElementById('modal_grade_level').value = currentGrade;
            document.getElementById('teacherGradeModal').classList.remove('hidden');
        }

        function closeTeacherGradeModal() {
            document.getElementById('teacherGradeModal').classList.add('hidden');
        }
    </script>
</body>
</html>
