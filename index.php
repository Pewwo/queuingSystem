<?php require 'auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Dashboard - Queue System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased h-screen overflow-hidden flex flex-col">
    <!-- Floating Show Header Button (Initially Hidden) -->
    <button id="show-header-btn" onclick="toggleHeader()" class="hidden fixed top-4 left-1/2 -translate-x-1/2 z-50 bg-white shadow-xl border border-slate-200 w-10 h-10 rounded-full flex items-center justify-center text-slate-400 hover:text-blue-600 transition-all opacity-0 hover:opacity-100">
        <span class="text-xl font-black">&darr;</span>
    </button>

    <div id="main-header" class="bg-white border-b border-slate-200 py-4 px-6 text-center shadow-sm relative shrink-0 transition-opacity duration-500">
        <button id="hide-header-btn" onclick="toggleHeader()" class="absolute top-4 left-6 w-8 h-8 rounded-full border border-slate-100 flex items-center justify-center text-slate-300 hover:text-blue-600 transition-colors">
            <span class="font-black">&uarr;</span>
        </button>
        <?php if (!isLoggedIn()): ?>
            <a href="login.php" class="absolute top-4 right-6 font-bold text-blue-600 hover:text-blue-800 text-xs tracking-wider uppercase bg-blue-50 px-3 py-1.5 rounded-lg">Staff Login</a>
        <?php else: ?>
            <div class="absolute top-4 right-6 flex items-center gap-3">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="logout.php" class="font-bold text-red-600 hover:text-red-800 text-xs tracking-wider uppercase bg-red-50 px-3 py-1.5 rounded-lg">Logout</a>
            </div>
        <?php endif; ?>
        <h1 class="text-2xl font-black tracking-tighter text-slate-900 mb-1">Queue Management System</h1>
        
        <?php if (isLoggedIn()): ?>
        <div class="flex justify-center gap-4 flex-wrap w-full max-w-2xl mx-auto mt-4">
            <a href="index.php" class="px-4 py-1 text-xs font-black border-b-2 border-blue-600 text-blue-600">Dashboard</a>
            <?php if (getUserRole() === 'admin'): ?>
                <a href="registration.php" class="px-4 py-1 text-xs font-bold text-slate-500 hover:text-slate-700">Registration</a>
                <a href="admin.php" class="px-4 py-1 text-xs font-bold text-slate-500 hover:text-slate-700">Admin Console</a>
            <?php elseif (getUserRole() === 'receptionist'): ?>
                <a href="receptionist_registration.php" class="px-4 py-1 text-xs font-bold text-slate-500 hover:text-slate-700">Registration</a>
            <?php elseif (getUserRole() === 'doctor'): ?>
                <a href="doctor.php" class="px-4 py-1 text-xs font-bold text-slate-500 hover:text-slate-700">Doctor Portal</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="w-full px-4 py-4 flex-1 overflow-hidden">
        <div id="dashboard-grid" class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-3 h-full">
            <!-- Populated via JS -->
        </div>
    </div>
    <script src="script.js"></script>
    <script>
        startDashboardPolling();
    </script>
</body>
</html>
