<nav class="bg-white shadow-md relative z-20">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center min-w-0">
                <a href="dashboard.php" class="text-xl sm:text-2xl font-bold text-blue-600 truncate">
                    Sukhdham Admin
                </a>
            </div>
            <div class="hidden md:flex items-center space-x-6">
                <a href="dashboard.php" class="text-gray-700 hover:text-blue-600">Dashboard</a>
                <a href="create.php" class="text-gray-700 hover:text-blue-600">Add Property</a>
                <a href="bookings.php" class="text-gray-700 hover:text-blue-600">Visit Requests</a>
                <a href="../index.html" class="text-gray-700 hover:text-blue-600">View Site</a>
                <a href="logout.php" class="text-gray-700 hover:text-red-600">Logout</a>
            </div>
            <button type="button" id="adminMenuBtn" class="md:hidden text-gray-700 text-2xl leading-none" aria-label="Toggle menu">
                &#9776;
            </button>
        </div>
        <div id="adminMobileMenu" class="hidden md:hidden pb-4 border-t border-gray-100">
            <div class="flex flex-col gap-3 pt-4">
                <a href="dashboard.php" class="text-gray-700 hover:text-blue-600">Dashboard</a>
                <a href="create.php" class="text-gray-700 hover:text-blue-600">Add Property</a>
                <a href="bookings.php" class="text-gray-700 hover:text-blue-600">Visit Requests</a>
                <a href="../index.html" class="text-gray-700 hover:text-blue-600">View Site</a>
                <a href="logout.php" class="text-gray-700 hover:text-red-600">Logout</a>
            </div>
        </div>
    </div>
</nav>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('adminMenuBtn');
        var menu = document.getElementById('adminMobileMenu');
        if (btn && menu) {
            btn.addEventListener('click', function () {
                menu.classList.toggle('hidden');
            });
        }
    });
</script>
