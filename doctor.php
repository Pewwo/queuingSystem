<?php

require 'db.php';

require 'auth.php';
requireRole(['admin', 'doctor']);

$clinic_name = "Your Clinic";
$clinic_id = "null";

if (isset($_SESSION['user_id']) && !isset($_SESSION['doctor_id'])) {
    $uid = intval($_SESSION['user_id']);
    $u_res = $conn->query("SELECT relation_id FROM users WHERE id = $uid");
    if ($u_row = $u_res->fetch_assoc()) {
        $_SESSION['doctor_id'] = $u_row['relation_id'];
    }
}

if (isset($_SESSION['relation_id']) && $_SESSION['relation_id'] !== null) {
    $rel_id = intval($_SESSION['relation_id']);
    $val = $conn->query("SELECT clinic_number FROM clinics WHERE id = $rel_id");
    if ($val && $c = $val->fetch_assoc()) {
        $clinic_name = $c['clinic_number'];
        $clinic_id = $rel_id;
    }
}
else if (getUserRole() === 'admin') {
    $clinic_name = "Admin Demo Mode (Clinic 101)";
    $clinic_id = 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Module</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased min-h-screen flex flex-col">
    <div class="bg-white border-b border-slate-200 py-5 px-8 flex flex-wrap justify-between items-center shadow-sm">
        <h1 class="text-3xl font-black text-blue-600 tracking-tight">Doctor Portal (<?php echo htmlspecialchars($_SESSION['username']); ?>)</h1>
        <div class="flex gap-3 mt-4 sm:mt-0">
            <button onclick="openSettingsModal()" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold py-2.5 px-6 rounded-xl transition-all shadow-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                Profile
            </button>
<?php if (getUserRole() === 'admin'): ?>
                <a href="registration.php" class="bg-slate-700 hover:bg-slate-800 text-white font-bold py-2.5 px-6 rounded-xl transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5">Registration</a>
                <a href="admin.php" class="bg-rose-500 hover:bg-rose-600 text-white font-bold py-2.5 px-6 rounded-xl transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5">Admin Console</a>
            <?php
endif; ?>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2.5 px-6 rounded-xl transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5">Logout</a>
        </div>
    </div>
    
    <div class="max-w-5xl mx-auto my-10 px-6 flex flex-col w-full flex-1">
        <!-- Main Content (Centered without Sidebar) -->
        <div class="flex-1 flex flex-col" id="doctor-main">
            <div id="doctor-panel" class="hidden bg-white rounded-3xl p-10 shadow-xl border border-slate-100 flex-1">
                <div class="flex flex-wrap justify-between items-start mb-6">
                    <h2 id="current-clinic-name" class="text-4xl font-black text-slate-900">Clinic Name</h2>
                    <div id="clinic-state-badge" class="px-5 py-2 rounded-full text-sm font-black tracking-widest uppercase text-white bg-slate-500">STATUS</div>
                </div>
                
                <div class="text-center border-2 border-slate-100 bg-slate-50/50 rounded-3xl p-16 mb-10 shadow-inner">
                    <h3 class="text-slate-400 font-extrabold uppercase tracking-widest text-sm mb-6">Currently Serving</h3>
                    <div>
                        <div id="serving-num" class="text-8xl md:text-9xl font-black text-blue-600 leading-none mb-6 tracking-tighter drop-shadow-sm">--</div>
                        <div id="serving-name" class="text-4xl font-bold text-slate-800 tracking-tight">No patient serving</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <button id="btn-call-next" class="bg-blue-600 hover:bg-blue-700 text-white font-extrabold py-5 px-6 rounded-2xl transition-all shadow-lg hover:shadow-xl hover:-translate-y-1 text-xl flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0" onclick="openCallNextModal()">Call Next Patient</button>
                    <button id="btn-mark-done" class="bg-emerald-500 hover:bg-emerald-600 text-white font-extrabold py-5 px-6 rounded-2xl transition-all shadow-lg hover:shadow-xl hover:-translate-y-1 text-xl flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:translate-y-0" onclick="markDone()">Mark Done</button>
                </div>
                <div id="action-msg" class="hidden mt-8 p-5 rounded-xl text-center font-bold text-lg"></div>
            </div>
        </div>
    </div>

    <!-- Call Next Confirmation Modal -->
    <div id="call-next-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Overlay -->
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeCallNextModal()"></div>
        
        <!-- Modal Content -->
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 relative z-10 transform scale-100 transition-all font-sans">
            <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mb-6 mx-auto">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 5l7 7-7 7M5 5l7 7-7 7"></path></svg>
            </div>
            
            <h3 class="text-2xl font-black text-slate-900 mb-2 text-center">Call Next Patient?</h3>
            <p class="text-slate-500 mb-8 font-medium text-center">This will move the queue forward and mark the current patient as completed.</p>
            
            <div class="flex gap-3 justify-center">
                <button onclick="closeCallNextModal()" class="flex-1 px-6 py-4 font-bold text-slate-600 hover:bg-slate-100 rounded-2xl transition-colors">Cancel</button>
                <button onclick="confirmCallNext()" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-2xl transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">Yes, Call Next</button>
            </div>
        </div>
    </div>
    <!-- Profile Settings Modal -->
    <div id="settings-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Overlay -->
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeSettingsModal()"></div>
        
        <!-- Modal Content -->
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 relative z-10 transform scale-100 transition-all font-sans">
            <h3 class="text-2xl font-black text-slate-900 mb-2">Profile Settings</h3>
            <p class="text-slate-500 mb-6 font-medium">Update your account information below.</p>
            
            <form id="settings-form" onsubmit="saveProfile(event)" class="space-y-4">
                <?php
$current_user = $_SESSION['username'];
$first_name = "";
$last_name = "";
if (isset($_SESSION['doctor_id'])) {
    $did = intval($_SESSION['doctor_id']);
    $res = $conn->query("SELECT name FROM doctors WHERE id = $did");
    if ($row = $res->fetch_assoc()) {
        $full_name = $row['name'];
        // Remove "Dr. " prefix if exists for editing
        $clean_name = preg_replace('/^Dr\.\s+/i', '', $full_name);
        $parts = explode(' ', $clean_name, 2);
        $first_name = $parts[0] ?? "";
        $last_name = $parts[1] ?? "";
    }
}
?>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">First Name</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3.5 font-bold text-slate-400">Dr.</span>
                            <input type="text" id="settings-first-name" value="<?php echo htmlspecialchars($first_name); ?>" class="w-full pl-11 pr-4 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all font-bold text-slate-800" required>
                        </div>
                    </div>
                    <div>
                        <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">Last Name</label>
                        <input type="text" id="settings-last-name" value="<?php echo htmlspecialchars($last_name); ?>" class="w-full px-5 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all font-bold text-slate-800" required>
                    </div>
                </div>
                <div>
                    <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">Username</label>
                    <input type="text" id="settings-username" value="<?php echo htmlspecialchars($current_user); ?>" class="w-full px-5 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all font-bold text-slate-800">
                </div>
                
                <div class="pt-2">
                    <button type="button" onclick="openPasswordModal()" class="w-full py-3 px-6 border-2 border-slate-200 text-slate-600 font-bold rounded-xl hover:bg-slate-50 hover:border-slate-300 transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path></svg>
                        Change Password
                    </button>
                </div>
                
                <div class="flex gap-3 justify-end pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeSettingsModal()" class="px-6 py-3 font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Cancel</button>
                    <button type="submit" id="save-profile-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">Save Profile</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Password Change Modal -->
    <div id="password-modal" class="hidden fixed inset-0 z-[55] flex items-center justify-center p-4">
        <!-- Overlay -->
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closePasswordModal()"></div>
        
        <!-- Modal Content -->
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 relative z-10 transform scale-100 transition-all font-sans">
            <h3 class="text-2xl font-black text-slate-900 mb-2">Change Password</h3>
            <p class="text-slate-500 mb-6 font-medium">Please verify your current password to continue.</p>
            
            <form id="password-form" onsubmit="savePassword(event)" class="space-y-4">
                <div class="relative">
                    <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">Old Password</label>
                    <div class="relative">
                        <input type="password" id="pass-old" placeholder="••••••••" class="w-full px-5 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all font-bold text-slate-800" required>
                        <button type="button" onclick="toggleSettingsPass('pass-old', 'eye-open-p-old', 'eye-closed-p-old')" class="absolute right-4 top-3.5 text-slate-400 hover:text-slate-600 focus:outline-none">
                            <svg id="eye-open-p-old" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <svg id="eye-closed-p-old" class="hidden w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                        </button>
                    </div>
                </div>

                <div class="relative border-t border-slate-100 pt-4 mt-4">
                    <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">New Password</label>
                    <div class="relative">
                        <input type="password" id="pass-new" placeholder="••••••••" class="w-full px-5 py-3 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all font-bold text-slate-800" required>
                        <button type="button" onclick="toggleSettingsPass('pass-new', 'eye-open-p-new', 'eye-closed-p-new')" class="absolute right-4 top-3.5 text-slate-400 hover:text-slate-600 focus:outline-none">
                            <svg id="eye-open-p-new" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            <svg id="eye-closed-p-new" class="hidden w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                        </button>
                    </div>
                </div>
                
                <div class="flex gap-3 justify-end pt-4">
                    <button type="button" onclick="closePasswordModal()" class="px-6 py-3 font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Cancel</button>
                    <button type="submit" id="save-pass-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Universal Alert Modal -->
    <div id="universal-alert-modal" class="hidden fixed inset-0 z-[60] flex items-center justify-center p-4">
        <!-- Overlay -->
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-md transition-opacity" onclick="closeAlert()"></div>
        
        <!-- Modal Content -->
        <div id="alert-content" class="bg-white rounded-[2rem] shadow-2xl max-w-sm w-full p-10 relative z-10 transform scale-100 transition-all font-sans text-center">
            <div id="alert-icon-container" class="w-20 h-20 rounded-full flex items-center justify-center mb-6 mx-auto">
                <!-- Icon will be injected by JS -->
            </div>
            
            <h3 id="alert-title" class="text-2xl font-black text-slate-900 mb-3">Alert</h3>
            <p id="alert-message" class="text-slate-500 mb-8 font-semibold leading-relaxed"></p>
            
            <button onclick="closeAlert()" id="alert-btn" class="w-full py-4 px-6 rounded-2xl font-black text-lg transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5">
                Dismiss
            </button>
        </div>
    </div>

    <script>
        const userRole = '<?php echo getUserRole(); ?>';
        const userRelationId = <?php echo $clinic_id; ?>;
        const userDoctorId = <?php echo $_SESSION['doctor_id'] ?? 0; ?>;
        const userClinicName = '<?php echo addslashes($clinic_name); ?>';
        const userId = <?php echo $_SESSION['user_id']; ?>;
    </script>
    <script src="script.js"></script>
</body>
</html>
