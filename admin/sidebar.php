<aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-indigo-800 text-white transition duration-300 transform -translate-x-full lg:translate-x-0">
    <div class="flex items-center justify-between p-4 border-b border-indigo-700">
        <h1 class="text-xl font-bold">Admin Panel</h1>
        <button id="closeSidebar" class="p-1 rounded-md lg:hidden hover:bg-indigo-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    
    <nav class="p-4">
        <ul class="space-y-2">
            <li>
                <a href="admin_dashboard.php" class="flex items-center space-x-2 py-2 px-3 rounded hover:bg-indigo-700 <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'bg-indigo-700' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="teachers.php" class="flex items-center space-x-2 py-2 px-3 rounded hover:bg-indigo-700 <?php echo basename($_SERVER['PHP_SELF']) == 'teachers.php' ? 'bg-indigo-700' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <span>Teachers</span>
                </a>
            </li>
            <li>
                <a href="salaries.php" class="flex items-center space-x-2 py-2 px-3 rounded hover:bg-indigo-700 <?php echo basename($_SERVER['PHP_SELF']) == 'salaries.php' ? 'bg-indigo-700' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Salaries</span>
                </a>
            </li>
            <li>
                <a href="attendance_monitor.php" class="flex items-center space-x-2 py-2 px-3 rounded hover:bg-indigo-700 <?php echo basename($_SERVER['PHP_SELF']) == 'attendance_monitor.php' ? 'bg-indigo-700' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>Attendance</span>
                </a>
            </li>

            <li class="border-t border-indigo-700 pt-2 mt-2">
                <a href="../logout.php" class="flex items-center space-x-2 py-2 px-3 rounded hover:bg-indigo-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<!-- Logout Confirmation Modal - Moved outside sidebar for better positioning -->
<div id="logoutModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <!-- Background overlay -->
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
    
    <!-- Modal centering wrapper -->
    <div class="flex items-center justify-center min-h-screen p-4 text-center">
        <!-- Modal panel -->
        <div class="relative bg-white rounded-lg overflow-hidden shadow-xl transform transition-all max-w-lg w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                        <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Confirm Logout
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Are you sure you want to log out from the admin panel? Any unsaved changes will be lost.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <a href="../logout.php" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm" id="confirmLogout">
                    Logout
                </a>
                <button type="button" onclick="hideLogoutModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function showLogoutModal() {
        document.getElementById('logoutModal').classList.remove('hidden');
    }
    
    function hideLogoutModal() {
        document.getElementById('logoutModal').classList.add('hidden');
    }
    
    function performLogout() {
        window.location.href = '../logout.php';
    }
    
    // Replace the logout link with a button that shows the modal
    document.addEventListener('DOMContentLoaded', function() {
        // Set up logout links to show the confirmation modal
        const logoutLinks = document.querySelectorAll('a[href="../logout.php"]');
        logoutLinks.forEach(link => {
            // Skip the link inside the modal
            if (!link.closest('#logoutModal')) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    showLogoutModal();
                });
            }
        });
        
        // Make sure the confirm button in the modal actually logs out
        const confirmButton = document.getElementById('confirmLogout');
        if (confirmButton) {
            confirmButton.addEventListener('click', function(e) {
                e.preventDefault();
                performLogout();
            });
        }
    });
</script>
