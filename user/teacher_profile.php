<?php
session_start();
include '../middleware.php'; // Include the middleware
userOnly(); // Restrict access to user-only pages
include '../database/db_connection.php'; // Include the database connection

$userId = $_SESSION['user_id'];
$successMessage = '';
$errorMessage = '';

// Check if teacher profile exists
$query = "SELECT * FROM teachers WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$teacherExists = $result->num_rows > 0;
$teacher = $teacherExists ? $result->fetch_assoc() : null;
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $subject = $_POST['subject'];
    $qualification = $_POST['qualification']; // Added qualification field
    $joiningDate = !empty($_POST['joining_date']) ? $_POST['joining_date'] : NULL;

    if ($teacherExists) {
        // Update existing profile
        $query = "UPDATE teachers SET name = ?, email = ?, phone = ?, subject = ?, qualification = ?, joining_date = ? WHERE user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssi", $name, $email, $phone, $subject, $qualification, $joiningDate, $userId);
        
        if ($stmt->execute()) {
            $successMessage = "Profile updated successfully!";
        } else {
            $errorMessage = "Error updating profile: " . $conn->error;
        }
    } else {
        // Create new profile
        if ($joiningDate === NULL) {
            // If joining date is NULL, use different query that won't include joining_date
            $query = "INSERT INTO teachers (user_id, name, email, phone, subject, qualification) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isssss", $userId, $name, $email, $phone, $subject, $qualification);
        } else {
            // Use original query with joining_date
            $query = "INSERT INTO teachers (user_id, name, email, phone, subject, qualification, joining_date) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("issssss", $userId, $name, $email, $phone, $subject, $qualification, $joiningDate);
        }
        
        if ($stmt->execute()) {
            $successMessage = "Profile created successfully!";
        } else {
            $errorMessage = "Error creating profile: " . $conn->error;
        }
    }
    $stmt->close();
    
    // Refresh teacher data after update
    $query = "SELECT * FROM teachers WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher = $result->fetch_assoc();
    $stmt->close();
}

// Handle delete request
if (isset($_POST['delete_profile']) && $teacherExists) {
    $query = "DELETE FROM teachers WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        $successMessage = "Profile deleted successfully!";
        $teacher = null;
        $teacherExists = false;
    } else {
        $errorMessage = "Error deleting profile: " . $conn->error;
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
    <title>Teacher Profile</title>
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
            
            // Confirm before delete
            const deleteForm = document.getElementById('deleteForm');
            if (deleteForm) {
                deleteForm.addEventListener('submit', function(e) {
                    if (!confirm('Are you sure you want to delete your profile? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            }
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
                    <h1 class="text-xl font-bold text-indigo-800">Teacher Profile</h1>
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

                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4"><?php echo $teacherExists ? 'Edit Profile' : 'Create Profile'; ?></h2>
                    
                    <form method="post" action="">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?php echo $teacherExists ? htmlspecialchars($teacher['name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="email">Email</label>
                                <input type="email" id="email" name="email" value="<?php echo $teacherExists ? htmlspecialchars($teacher['email'], ENT_QUOTES, 'UTF-8') : ''; ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" value="<?php echo $teacherExists ? htmlspecialchars($teacher['phone'], ENT_QUOTES, 'UTF-8') : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="subject">Subject</label>
                                <input type="text" id="subject" name="subject" value="<?php echo $teacherExists ? htmlspecialchars($teacher['subject'], ENT_QUOTES, 'UTF-8') : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="qualification">Qualification</label>
                                <select id="qualification" name="qualification" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">Select Qualification</option>
                                    <option value="Bachelor's" <?php echo ($teacherExists && $teacher['qualification'] == "Bachelor's") ? 'selected' : ''; ?>>Bachelor's Degree</option>
                                    <option value="Master's" <?php echo ($teacherExists && $teacher['qualification'] == "Master's") ? 'selected' : ''; ?>>Master's Degree</option>
                                    <option value="PhD" <?php echo ($teacherExists && $teacher['qualification'] == "PhD") ? 'selected' : ''; ?>>PhD</option>
                                    <option value="Postdoc" <?php echo ($teacherExists && $teacher['qualification'] == "Postdoc") ? 'selected' : ''; ?>>Postdoctoral</option>
                                    <option value="Other" <?php echo ($teacherExists && $teacher['qualification'] == "Other") ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="joining_date">Joining Date</label>
                                <input type="date" id="joining_date" name="joining_date" value="<?php echo $teacherExists ? htmlspecialchars($teacher['joining_date'], ENT_QUOTES, 'UTF-8') : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <div class="flex justify-between">
                            <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                <?php echo $teacherExists ? 'Update Profile' : 'Create Profile'; ?>
                            </button>
                            
                            <?php if ($teacherExists): ?>
                                <form id="deleteForm" method="post" action="">
                                    <input type="hidden" name="delete_profile" value="1">
                                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                        Delete Profile
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>
</body>
</html>
