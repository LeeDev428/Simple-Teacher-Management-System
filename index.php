<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Welcome | Teacher & Staff Management</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      zoom: 0.9;
      -moz-transform: scale(0.9);
      -moz-transform-origin: 0 0;
    }
    .feature-icon {
      font-size: 1.25rem;
      width: 2.5rem;
      height: 2.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
    }
  </style>
</head>
<body class="bg-blue-50 min-h-screen">
  <header class="bg-indigo-700 text-white py-4">
    <div class="container mx-auto text-center">
      <h1 class="text-3xl font-bold">Teacher & Staff Management System</h1>
    </div>
  </header>
  
  <main class="container mx-auto px-4 py-8 flex flex-col lg:flex-row">
    <!-- Left section with text -->
    <div class="lg:w-1/2 pr-0 lg:pr-12">
      <h2 class="text-4xl font-bold text-indigo-800 mb-3">Streamline School Administration</h2>
      <p class="text-gray-700 mb-8">Comprehensive solution for managing teachers, staff, schedules, and performance evaluations efficiently in one centralized platform.</p>
      
      <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8 mb-8">
        <!-- Teacher Profiles -->
        <div class="flex items-start">
          <div class="bg-blue-100 rounded-lg feature-icon mr-4 text-indigo-600">
            <i class="fas fa-user"></i>
          </div>
          <div>
            <h3 class="font-bold text-indigo-700">Teacher Profiles</h3>
            <p class="text-sm text-gray-600">Centralized database for all personnel</p>
          </div>
        </div>
        
        <!-- Attendance & Leave Tracking -->
        <div class="flex items-start">
          <div class="bg-blue-100 rounded-lg feature-icon mr-4 text-indigo-600">
            <i class="fas fa-clipboard-check"></i>
          </div>
          <div>
            <h3 class="font-bold text-indigo-700">Attendance & Leave Tracking</h3>
            <p class="text-sm text-gray-600">Monitor and evaluate work hours</p>
          </div>
        </div>
        
        <!-- Teaching Load Management -->
        <div class="flex items-start">
          <div class="bg-blue-100 rounded-lg feature-icon mr-4 text-indigo-600">
            <i class="fas fa-chalkboard"></i>
          </div>
          <div>
            <h3 class="font-bold text-indigo-700">Teaching Load Management</h3>
            <p class="text-sm text-gray-600">Organize subjects and class assignments</p>
          </div>
        </div>
        
        <!-- Compensation Administration -->
        <div class="flex items-start">
          <div class="bg-blue-100 rounded-lg feature-icon mr-4 text-indigo-600">
            <i class="fas fa-lock"></i>
          </div>
          <div>
            <h3 class="font-bold text-indigo-700">Compensation Administration</h3>
            <p class="text-sm text-gray-600">Secure processing of financial benefits</p>
          </div>
        </div>
      </div>
      
      <div class="flex space-x-4">
        <a href="login.php" class="px-8 py-2 bg-indigo-600 text-white rounded-md font-medium hover:bg-indigo-700 transition">Login</a>
        <a href="register.php" class="px-8 py-2 bg-white text-indigo-700 border border-indigo-600 rounded-md font-medium hover:bg-indigo-50 transition">Register</a>
      </div>
    </div>
    
    <!-- Right section with illustration and system overview -->
    <div class="lg:w-1/2 mt-10 lg:mt-0">
      <img src="assets/images/teacher-management-hero.png" alt="Teacher Management System" class="w-1/3 mx-auto">
      
      <div class="bg-white p-6 rounded-lg shadow-sm mt-6">
      <div class="flex items-center justify-between border-b pb-2 mb-4">
      <h3 class="font-bold text-lg text-indigo-800">System Overview</h3>
      <span class="bg-green-100 text-green-800 px-3 py-0.5 rounded-full text-xs font-medium">Live Demo</span>
      </div>
      <p class="text-gray-600">Our system is currently deployed in over 200+ educational institutions, helping manage 5000+ teachers and staff members efficiently.</p>
      </div>
    </div>
    </main>
  <footer class="bg-indigo-700 text-white py-6 mt-12">
    <div class="container mx-auto px-4">
      <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="mb-6 md:mb-0">
          <h2 class="text-2xl font-bold">Teacher & Staff Management</h2>
          <p class="text-indigo-200 mt-1">Empowering educational excellence through effective administration</p>
        </div>
        <div class="flex space-x-4">
          <a href="#" class="text-white hover:text-indigo-200 transition"><i class="fab fa-facebook-f"></i></a>
          <a href="#" class="text-white hover:text-indigo-200 transition"><i class="fab fa-twitter"></i></a>
          <a href="#" class="text-white hover:text-indigo-200 transition"><i class="fab fa-linkedin-in"></i></a>
        </div>
      </div>
      <div class="border-t border-indigo-600 mt-6 pt-6 text-center text-indigo-200">
        <p>&copy; <?php echo date('Y'); ?> Teacher & Staff Management System. All rights reserved.</p>
      </div>
    </div>
  </footer>
</body>
</html>
