<?php
session_start();
include 'database/db_connection.php';

$success = '';
$error = '';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: user/dashboard.php");
    exit();
}

// Process registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirmPassword)) {
        $error = "All fields are required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        // Check if username or email already exists
        $query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = "Username or email already exists";
        } else {
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user
            $query = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sss", $username, $email, $hashedPassword);
            
            if ($stmt->execute()) {
                $success = "Registration successful! Please <a href='login.php' class='font-medium underline'>login</a> to continue.";
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register for Teacher Management System">
    <meta name="author" content="School Administration">
    <meta name="theme-color" content="#4F46E5">
    <title>Register | School Management System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <!-- Custom Styles -->
    <style>
        .register-bg {
            background-image: linear-gradient(rgba(79, 70, 229, 0.1), rgba(79, 70, 229, 0.05)), url('assets/images/school-pattern.png');
            background-size: cover;
        }
    </style>
</head>
<body class="bg-gray-50 register-bg min-h-screen flex flex-col justify-center py-12">
    <div class="container mx-auto px-4">
        <div class="flex content-center items-center justify-center">
            <div class="w-full lg:w-1/3 md:w-1/2 sm:w-2/3">
                <!-- Logo & Header -->
                <div class="text-center mb-6">
                    <h1 class="text-indigo-600 text-4xl font-bold">Teacher Portal</h1>
                    <p class="text-gray-600 mt-2">Create your teacher account</p>
                </div>
                
                <!-- Register Card -->
                <div class="relative flex flex-col min-w-0 break-words w-full mb-6 shadow-lg rounded-lg bg-white border-0">
                    <div class="flex-auto px-8 py-10">
                        <div class="text-gray-700 text-center mb-4 font-bold">
                            <h2 class="text-xl">Sign Up</h2>
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                                <p><?php echo $success; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                                <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="relative w-full mb-4">
                                <label class="block text-gray-700 text-sm font-medium mb-2" for="username">
                                    Username
                                </label>
                                <div class="flex">
                                    <div class="w-10 z-10 pl-1 text-center pointer-events-none flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <input type="text" id="username" name="username" placeholder="Username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                                        class="px-3 py-3 placeholder-gray-400 text-gray-700 bg-white rounded text-sm shadow focus:outline-none focus:ring-2 focus:ring-indigo-600 w-full pl-10"
                                        required autofocus />
                                </div>
                            </div>
                            
                            <div class="relative w-full mb-4">
                                <label class="block text-gray-700 text-sm font-medium mb-2" for="email">
                                    Email
                                </label>
                                <div class="flex">
                                    <div class="w-10 z-10 pl-1 text-center pointer-events-none flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
                                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                        </svg>
                                    </div>
                                    <input type="email" id="email" name="email" placeholder="Email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                                        class="px-3 py-3 placeholder-gray-400 text-gray-700 bg-white rounded text-sm shadow focus:outline-none focus:ring-2 focus:ring-indigo-600 w-full pl-10"
                                        required />
                                </div>
                            </div>
                            
                            <div class="relative w-full mb-4">
                                <label class="block text-gray-700 text-sm font-medium mb-2" for="password">
                                    Password
                                </label>
                                <div class="flex">
                                    <div class="w-10 z-10 pl-1 text-center pointer-events-none flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <input type="password" id="password" name="password" placeholder="Password" 
                                        class="px-3 py-3 placeholder-gray-400 text-gray-700 bg-white rounded text-sm shadow focus:outline-none focus:ring-2 focus:ring-indigo-600 w-full pl-10"
                                        required minlength="6" />
                                </div>
                                <small class="text-gray-500 mt-1 block">Minimum 6 characters</small>
                            </div>
                            
                            <div class="relative w-full mb-4">
                                <label class="block text-gray-700 text-sm font-medium mb-2" for="confirm_password">
                                    Confirm Password
                                </label>
                                <div class="flex">
                                    <div class="w-10 z-10 pl-1 text-center pointer-events-none flex items-center justify-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm Password" 
                                        class="px-3 py-3 placeholder-gray-400 text-gray-700 bg-white rounded text-sm shadow focus:outline-none focus:ring-2 focus:ring-indigo-600 w-full pl-10"
                                        required minlength="6" />
                                </div>
                            </div>
                            
                            <div class="text-center mt-6">
                                <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-3 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-300 font-medium">
                                    Register
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Footer Links -->
                <div class="flex flex-wrap mt-2 text-center justify-center">
                    <div class="w-full">
                        <span class="text-gray-600">Already have an account?</span>
                        <a href="login.php" class="text-indigo-600 hover:text-indigo-800 font-medium ml-1">
                            Sign in
                        </a>
                    </div>
                </div>
                
                <!-- Copyright -->
                <div class="text-center text-gray-500 text-sm mt-6">
                    &copy; <?php echo date('Y'); ?> School Management System. All Rights Reserved.
                </div>
            </div>
        </div>
    </div>
    
    <!-- Form validation script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            form.addEventListener('submit', function(event) {
                if (password.value !== confirmPassword.value) {
                    event.preventDefault();
                    alert('Passwords do not match!');
                }
            });
        });
    </script>
</body>
</html>
