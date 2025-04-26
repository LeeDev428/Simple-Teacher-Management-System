<?php
session_start();
include 'database/db_connection.php';

$error = '';

// Generate CSRF token if it doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
        header("Location: admin/admin_dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit();
}

// Process login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security validation failed. Please try again.";
    } else {
        $email = isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // Basic validation
        if (empty($email) || empty($password)) {
            $error = "All fields are required";
        } else {
            // Use prepared statements to prevent SQL injection
            $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Successful login, start session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['is_admin'] = $user['is_admin'];

                    // Redirect based on is_admin value
                    if ($user['is_admin'] == 1) {
                        header("Location: admin/admin_dashboard.php");
                    } else {
                        header("Location: user/dashboard.php");
                    }
                    exit();
                } else {
                    $error = "Incorrect password";
                }
            } else {
                $error = "No user found with this email";
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to Teacher Management System">
    <meta name="author" content="School Administration">
    <meta name="theme-color" content="#4F46E5">
    <title>Login | School Management System</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <!-- Custom Styles -->
    <style>
        .login-bg {
            background-image: linear-gradient(rgba(79, 70, 229, 0.1), rgba(79, 70, 229, 0.05)), url('assets/images/school-pattern.png');
            background-size: cover;
        }
    </style>
</head>
<body class="bg-gray-50 login-bg min-h-screen flex flex-col justify-center">
    <div class="container mx-auto px-4 h-full">
        <div class="flex content-center items-center justify-center h-full">
            <div class="w-full lg:w-1/3 md:w-1/2 sm:w-2/3">
                <!-- Logo & Header -->
                <div class="text-center mb-6">
                    <h1 class="text-indigo-600 text-4xl font-bold">Teacher Portal</h1>
                    <p class="text-gray-600 mt-2">Access your teacher dashboard</p>
                </div>
                
                <!-- Login Card -->
                <div class="relative flex flex-col min-w-0 break-words w-full mb-6 shadow-lg rounded-lg bg-white border-0">
                    <div class="flex-auto px-8 py-10">
                        <div class="text-gray-700 text-center mb-4 font-bold">
                            <h2 class="text-xl">Sign In With Credentials</h2>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                                <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            
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
                                    <input type="email" id="email" name="email" placeholder="Email"
                                        class="px-3 py-3 placeholder-gray-400 text-gray-700 bg-white rounded text-sm shadow focus:outline-none focus:ring-2 focus:ring-indigo-600 w-full pl-10"
                                        required autofocus />
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
                                        required />
                                </div>
                            </div>
                            
                            <div class="flex items-center mb-4">
                                <input id="remember" name="remember" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="remember" class="ml-2 block text-sm text-gray-700">
                                    Remember me
                                </label>
                            </div>
                            
                            <div class="text-center mt-6">
                                <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-3 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-300 font-medium">
                                    Sign In
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Footer Links -->
                <div class="flex flex-wrap mt-2 text-center justify-center">
                    <div class="w-full">
                        <a href="register.php" class="text-indigo-600 hover:text-indigo-800 font-medium">
                            Create new account
                        </a>
                        <span class="text-gray-400 mx-2">|</span>
                        <a href="forgot-password.php" class="text-indigo-600 hover:text-indigo-800 font-medium">
                            Forgot password?
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
</body>
</html>
