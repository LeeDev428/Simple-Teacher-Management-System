<?php
session_start();
include '../middleware.php'; // Adjusted path for middleware
adminOnly(); // Restrict access to admin-only users
include '../database/db_connection.php'; // Include the database connection

// Fetch monthly sales for the line graph
$monthlySales = [];
for ($month = 1; $month <= 12; $month++) {
    $query = "SELECT SUM(total_amount) AS total FROM orders WHERE status = 'Completed' AND MONTH(created_at) = ? AND YEAR(created_at) = YEAR(CURDATE())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $month);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $monthlySales[] = $row['total'] ? (float)$row['total'] : 0; // Default to 0 if no sales
    $stmt->close();
}

// Fetch sales per product for the donut graph
$productSales = [];
$productLabels = [];
$query = "SELECT items.name AS product_name, SUM(orders.total_amount) AS total_sales 
          FROM orders 
          JOIN items ON orders.item_id = items.id 
          WHERE orders.status = 'Completed' 
          GROUP BY orders.item_id";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $productLabels[] = $row['product_name'];
    $productSales[] = (float)$row['total_sales'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@latest/dist/umd/lucide.min.js"></script>
    <script>
        // Initialize lucide icons after the DOM is fully loaded
        document.addEventListener("DOMContentLoaded", () => {
            lucide.createIcons();
        });

        // Toggle sidebar visibility
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        // Minimize sidebar
        function minimizeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            sidebar.classList.toggle('w-64');
            sidebar.classList.toggle('w-16');
            mainContent.classList.toggle('lg:ml-64');
            mainContent.classList.toggle('lg:ml-16');
            const sidebarText = document.querySelectorAll('.sidebar-text');
            sidebarText.forEach(text => text.classList.toggle('hidden'));
        }

        // Toggle dropdown visibility
        function toggleDropdown() {
            const dropdown = document.getElementById('dropdownMenu');
            dropdown.classList.toggle('hidden');
        }
    </script>
</head>
<body class="bg-gradient-to-br from-green-100 via-yellow-100 to-white min-h-screen flex overflow-x-auto">
    <!-- Sidebar -->
    <aside id="sidebar" class="bg-green-600 text-white w-64 min-h-screen fixed transform -translate-x-full lg:translate-x-0 transition-transform duration-300 border-r border-green-800">
        <div class="p-6 flex justify-between items-center">
            <h2 class="text-2xl font-bold sidebar-text">Admin Panel</h2>
            <button onclick="minimizeSidebar()" class="text-white hover:text-gray-300">
                <i data-lucide="chevron-left"></i>
            </button>
        </div>
        <nav class="mt-6">
            <a href="admin_dashboard.php" class="block px-6 py-3 hover:bg-green-700 transition sidebar-text">Dashboard</a>
            <a href="add_item.php" class="block px-6 py-3 hover:bg-green-700 transition sidebar-text">Item Management</a>
            <a href="orders.php" class="block px-6 py-3 hover:bg-green-700 transition sidebar-text">Orders</a>
            <a href="transaction.php" class="block px-6 py-3 hover:bg-green-700 transition sidebar-text">Transaction</a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div id="mainContent" class="flex-1 lg:ml-64 transition-all duration-300">
        <header class="bg-green-600 text-white py-4 shadow-lg">
            <div class="container mx-auto flex justify-center items-center">
                <h1 class="text-2xl font-bold">Admin Dashboard</h1>
            </div>
            <div class="absolute top-4 right-4">
                <button onclick="toggleDropdown()" class="flex items-center space-x-2 px-4 py-2 bg-green-700 text-white rounded-lg hover:bg-green-800 transition">
                    <span class="text-lg font-semibold"><?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <i data-lucide="chevron-down"></i>
                </button>
                <div id="dropdownMenu" class="absolute right-0 mt-2 w-48 bg-white text-gray-700 rounded-lg shadow-lg hidden">
                    <a href="../logout.php" class="block px-4 py-2 rounded-lg hover:bg-gray-100 transition">Logout</a>
                </div>
            </div>
        </header>

        <main class="container mx-auto mt-12">
            <!-- Graphs Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Line Graph -->
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h2 class="text-xl font-bold text-gray-700 mb-4">Sales Monthly</h2>
                    <div id="lineChart"></div>
                </div>

                <!-- Donut Graph -->
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h2 class="text-xl font-bold text-gray-700 mb-4">Sales per Product</h2>
                    <div id="donutChart"></div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Line Graph: Sales Monthly
        var lineOptions = {
            chart: {
                type: 'line',
                height: 350
            },
            series: [{
                name: 'Sales',
                data: <?php echo json_encode($monthlySales); ?>
            }],
            xaxis: {
                categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
            },
            title: {
                text: 'Monthly Sales',
                align: 'center'
            }
        };

        var lineChart = new ApexCharts(document.querySelector("#lineChart"), lineOptions);
        lineChart.render();

        // Donut Graph: Sales per Product
        var donutOptions = {
            chart: {
                type: 'donut',
                height: 350
            },
            series: <?php echo json_encode($productSales); ?>,
            labels: <?php echo json_encode($productLabels); ?>,
            title: {
                text: 'Sales per Product',
                align: 'center'
            }
        };

        var donutChart = new ApexCharts(document.querySelector("#donutChart"), donutOptions);
        donutChart.render();
    </script>
</body>
</html>
