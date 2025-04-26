<div id="sidebar" class="bg-indigo-800 text-white h-screen fixed left-0 top-0 w-64 transition-all duration-300 z-30 transform">
    <div class="p-4 flex justify-between items-center border-b border-indigo-700">
        <h2 class="text-xl font-bold">Teacher Portal</h2>
        <button id="closeSidebar" class="lg:hidden text-white">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    
    <div class="p-4">
        <div class="flex items-center space-x-4 mb-6">
            <div class="h-10 w-10 rounded-full bg-indigo-600 flex items-center justify-center">
                <span class="text-lg font-bold"><?php echo substr($_SESSION['username'], 0, 1); ?></span>
            </div>
            <div>
                <h3 class="font-medium"><?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p class="text-xs text-indigo-300">Teacher</p>
            </div>
        </div>
        
        <nav>
            <ul class="space-y-2">
                <li>
                    <a href="dashboard.php" class="flex items-center space-x-2 py-2 px-3 rounded hover:bg-indigo-700 <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-indigo-700' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="teacher_profile.php" class="flex items-center space-x-2 py-2 px-3 rounded hover:bg-indigo-700 <?php echo basename($_SERVER['PHP_SELF']) == 'teacher_profile.php' ? 'bg-indigo-700' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <span>My Profile</span>
                    </a>
                </li>
                <li>
                    <a href="assignments.php" class="flex items-center space-x-2 py-2 px-3 rounded hover:bg-indigo-700 <?php echo basename($_SERVER['PHP_SELF']) == 'assignments.php' || basename($_SERVER['PHP_SELF']) == 'view_assignment.php' ? 'bg-indigo-700' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <span>Assignments</span>
                    </a>
                </li>
                <li>
                    <a href="attendance.php" class="flex items-center space-x-2 py-2 px-3 rounded hover:bg-indigo-700 <?php echo basename($_SERVER['PHP_SELF']) == 'attendance.php' ? 'bg-indigo-700' : ''; ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <span>Attendance</span>
                    </a>
                </li>
                <li>
                    <a href="../logout.php" class="flex items-center space-x-2 py-2 px-3 rounded hover:bg-indigo-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>
