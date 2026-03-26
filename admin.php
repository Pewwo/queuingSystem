<?php

require 'db.php';

require 'auth.php';
requireRole(['admin']); // Restrict to admin only

$limit = 10;
$c_page = isset($_GET['c_page']) ? max(1, intval($_GET['c_page'])) : 1;
$d_page = isset($_GET['d_page']) ? max(1, intval($_GET['d_page'])) : 1;
$active_tab = $_GET['tab'] ?? 'clinics';
if ($active_tab === 'archives')
    $active_tab = 'archived-clinics';

$q_c = trim($_GET['q_c'] ?? '');
$q_d = trim($_GET['q_d'] ?? '');

// Archives Variables
$a_c_page = isset($_GET['a_c_page']) ? max(1, intval($_GET['a_c_page'])) : 1;
$a_d_page = isset($_GET['a_d_page']) ? max(1, intval($_GET['a_d_page'])) : 1;
$c_offset = ($c_page - 1) * $limit;
$d_offset = ($d_page - 1) * $limit;
$a_c_offset = ($a_c_page - 1) * $limit;
$a_d_offset = ($a_d_page - 1) * $limit;

// Clinic Query conditions
$c_where = " WHERE is_archived = 0 ";
$c_params = [];
$c_types = "";
if (!empty($q_c)) {
    $c_where .= " AND clinic_number LIKE ? ";
    $c_params[] = "%$q_c%";
    $c_types .= "s";
}

// Doctor Query conditions
$d_where = " WHERE is_archived = 0 ";
$d_params = [];
$d_types = "";
if (!empty($q_d)) {
    $d_where .= " AND name LIKE ? ";
    $d_params[] = "%$q_d%";
    $d_types .= "s";
}

// Count Clinics
$count_c_query = "SELECT COUNT(*) FROM clinics" . $c_where;
if (!empty($q_c)) {
    $stmt = $conn->prepare($count_c_query);
    $stmt->bind_param($c_types, ...$c_params);
    $stmt->execute();
    $total_c = $stmt->get_result()->fetch_row()[0];
}
else {
    $total_c = $conn->query($count_c_query)->fetch_row()[0];
}

// Count Doctors
$count_d_query = "SELECT COUNT(*) FROM doctors" . $d_where;
if (!empty($q_d)) {
    $stmt = $conn->prepare($count_d_query);
    $stmt->bind_param($d_types, ...$d_params);
    $stmt->execute();
    $total_d = $stmt->get_result()->fetch_row()[0];
}
else {
    $total_d = $conn->query($count_d_query)->fetch_row()[0];
}

// Count Archived Clinics
$count_ac_query = "SELECT COUNT(*) FROM clinics WHERE is_archived = 1";
$total_ac = $conn->query($count_ac_query)->fetch_row()[0];

// Count Archived Accounts
$count_ad_query = "SELECT COUNT(*) FROM users WHERE is_archived = 1";
$total_ad = $conn->query($count_ad_query)->fetch_row()[0];

$c_pages = max(1, ceil($total_c / $limit));
$d_pages = max(1, ceil($total_d / $limit));
$a_c_pages = max(1, ceil($total_ac / $limit));
$a_d_pages = max(1, ceil($total_ad / $limit));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - IP Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        /* Hide number spin arrows natively */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            margin: 0; 
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased min-h-screen flex flex-col">
    <!-- Navbar -->
    <div class="bg-gray-900 text-white border-b border-gray-800 py-5 px-8 flex flex-wrap justify-between items-center shadow-lg">
        <h1 class="text-3xl font-black text-rose-500 tracking-tight flex items-center gap-3">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
            System Admin
    </h1>
    </div>
    
    <!-- Main Content -->
    <div class="max-w-6xl mx-auto mt-8 mb-4 px-6 w-full flex-1">
        <div class="mb-4">
            <a href="index.php" class="font-bold text-slate-600 hover:text-blue-600 text-sm tracking-wider uppercase bg-white shadow-sm px-4 py-2 rounded-lg inline-flex items-center gap-2 transition-colors border border-slate-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back
            </a>
        </div>
        
        <!-- Tabs -->
        <div class="flex gap-4 mb-6 border-b border-slate-200 px-4 overflow-x-auto">
            <button onclick="switchTab('clinics')" id="tab-clinics" class="tab-btn pb-4 px-4 text-lg font-bold border-b-4 border-blue-600 text-blue-600 transition-colors whitespace-nowrap">Clinic Config</button>
            <button onclick="switchTab('doctors')" id="tab-doctors" class="tab-btn pb-4 px-4 text-lg font-bold border-b-4 border-transparent text-slate-500 hover:text-slate-700 transition-colors whitespace-nowrap">Account Management</button>
            <button onclick="switchTab('archived-clinics')" id="tab-archived-clinics" class="tab-btn pb-4 px-4 text-lg font-bold border-b-4 border-transparent text-slate-500 hover:text-slate-700 transition-colors whitespace-nowrap">Archived Clinics</button>
            <button onclick="switchTab('archived-accs')" id="tab-archived-accs" class="tab-btn pb-4 px-4 text-lg font-bold border-b-4 border-transparent text-slate-500 hover:text-slate-700 transition-colors whitespace-nowrap">Archived Accs</button>
        </div>

        <!-- Clinic Content -->
        <div id="content-clinics" class="tab-content block">
            <div class="bg-white rounded-3xl p-10 shadow-xl border border-slate-100">
                
                <div class="flex justify-between items-end mb-8 border-b border-slate-200 pb-6 flex-wrap gap-4">
                <div>
                    <h2 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Clinic Network Configuration</h2>
                    <p class="text-slate-500 font-medium">Assign static IP addresses to physical clinic terminals.</p>
                </div>
                <div class="flex items-center gap-4 flex-wrap mt-4 sm:mt-0">
                    <form method="GET" action="admin.php" class="relative">
                        <input type="hidden" name="tab" value="clinics">
                        <input type="hidden" name="q_d" value="<?php echo htmlspecialchars($q_d); ?>">
                        <input type="hidden" name="d_page" value="<?php echo $d_page; ?>">
                        <input type="hidden" name="a_c_page" value="<?php echo $a_c_page; ?>">
                        <input type="hidden" name="a_d_page" value="<?php echo $a_d_page; ?>">
                        <input type="text" name="q_c" value="<?php echo htmlspecialchars($q_c); ?>" placeholder="Search clinics..." class="pl-10 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-all text-sm w-64">
                        <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </form>
                    <button id="add-clinic-btn" onclick="openAddClinicModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-extrabold py-3.5 px-6 rounded-xl transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5 text-md flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Add New Clinic
                    </button>
                </div>
            </div>

            <div id="save-msg" class="hidden mb-6 p-4 rounded-xl flex items-center gap-3 font-bold"></div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b-2 border-slate-200 text-slate-500 uppercase tracking-widest text-sm">
                            <th class="py-4 px-6 font-bold w-1/4">Clinic Name</th>
                            <th class="py-4 px-6 font-bold w-1/2">Assigned IP Address v4</th>
                            <th class="py-4 px-6 font-bold w-1/4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
$clins_query = "SELECT id, clinic_number, ip_address FROM clinics" . $c_where . " ORDER BY LENGTH(clinic_number) ASC, clinic_number ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($clins_query);
if (!empty($q_c)) {
    $merged_params = array_merge($c_params, [$limit, $c_offset]);
    $stmt->bind_param($c_types . "ii", ...$merged_params);
}
else {
    $stmt->bind_param("ii", $limit, $c_offset);
}
$stmt->execute();
$clins = $stmt->get_result();

if ($clins->num_rows == 0):
?>
                        <tr>
                            <td colspan="3" class="py-8 text-center text-slate-500 font-medium">No clinics found.</td>
                        </tr>
                        <?php
else:
    while ($c = $clins->fetch_assoc()):
        // Burst the IP address string "192.168.1.1" into an array [192, 168, 1, 1]
        $ip = $c['ip_address'] ? explode('.', $c['ip_address']) : ['', '', '', ''];
?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" data-clinic-id="<?php echo $c['id']; ?>">
                            <td class="py-5 px-6">
                                <span class="font-extrabold text-xl text-slate-800"><?php echo htmlspecialchars($c['clinic_number']); ?></span>
                            </td>
                            <td class="py-5 px-6">
                                <div class="flex items-end gap-2 ip-group">
                                    <input type="number" min="0" max="255" class="ip-octet w-20 px-3 py-3 bg-slate-100 border-2 border-slate-200 rounded-xl text-center font-bold text-xl text-blue-800 disabled:opacity-60 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed focus:outline-none focus:border-blue-500 focus:bg-white transition-all placeholder:text-slate-300" placeholder="192" value="<?php echo htmlspecialchars($ip[0]); ?>" disabled>
                                    <span class="text-3xl font-black text-slate-300 pb-2">.</span>
                                    <input type="number" min="0" max="255" class="ip-octet w-20 px-3 py-3 bg-slate-100 border-2 border-slate-200 rounded-xl text-center font-bold text-xl text-blue-800 disabled:opacity-60 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed focus:outline-none focus:border-blue-500 focus:bg-white transition-all placeholder:text-slate-300" placeholder="168" value="<?php echo htmlspecialchars($ip[1]); ?>" disabled>
                                    <span class="text-3xl font-black text-slate-300 pb-2">.</span>
                                    <input type="number" min="0" max="255" class="ip-octet w-20 px-3 py-3 bg-slate-100 border-2 border-slate-200 rounded-xl text-center font-bold text-xl text-blue-800 disabled:opacity-60 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed focus:outline-none focus:border-blue-500 focus:bg-white transition-all placeholder:text-slate-300" placeholder="1" value="<?php echo htmlspecialchars($ip[2]); ?>" disabled>
                                    <span class="text-3xl font-black text-slate-300 pb-2">.</span>
                                    <input type="number" min="0" max="255" class="ip-octet w-20 px-3 py-3 bg-slate-100 border-2 border-slate-200 rounded-xl text-center font-bold text-xl text-blue-800 disabled:opacity-60 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed focus:outline-none focus:border-blue-500 focus:bg-white transition-all placeholder:text-slate-300" placeholder="100" value="<?php echo htmlspecialchars($ip[3] ?? ''); ?>" disabled>
                                </div>
                            </td>
                            <td class="py-5 px-6 text-center">
                                <div class="flex justify-center gap-2">
                                    <button onclick="enableEdit(<?php echo $c['id']; ?>)" id="edit-btn-<?php echo $c['id']; ?>" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-2.5 px-6 rounded-lg transition-all shadow-sm">Edit</button>
                                    <button onclick="saveClinicIP(<?php echo $c['id']; ?>)" id="save-btn-<?php echo $c['id']; ?>" class="hidden bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg transition-all shadow-md">Save</button>
                                    <button onclick="archiveClinic(<?php echo $c['id']; ?>)" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold py-2.5 px-4 rounded-lg transition-all shadow-sm" title="Archive Clinic">
                                        <svg class="w-5 h-5 block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php
    endwhile;
endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($c_pages > 1): ?>
            <div class="flex justify-between items-center mt-6">
                <div class="text-sm font-bold text-slate-500">
                    Showing page <?php echo $c_page; ?> of <?php echo $c_pages; ?>
                </div>
                <div class="flex gap-2">
                    <?php if ($c_page > 1): ?>
                    <a href="?tab=clinics&c_page=<?php echo $c_page - 1; ?>&d_page=<?php echo $d_page; ?>&a_c_page=<?php echo $a_c_page; ?>&a_d_page=<?php echo $a_d_page; ?>&q_c=<?php echo urlencode($q_c); ?>&q_d=<?php echo urlencode($q_d); ?>" class="px-4 py-2 border border-slate-200 rounded-lg shadow-sm text-sm font-bold text-slate-600 hover:bg-slate-50 transition-colors">Previous</a>
                    <?php
    endif; ?>
                    <?php if ($c_page < $c_pages): ?>
                    <a href="?tab=clinics&c_page=<?php echo $c_page + 1; ?>&d_page=<?php echo $d_page; ?>&a_c_page=<?php echo $a_c_page; ?>&a_d_page=<?php echo $a_d_page; ?>&q_c=<?php echo urlencode($q_c); ?>&q_d=<?php echo urlencode($q_d); ?>" class="px-4 py-2 border border-slate-200 rounded-lg shadow-sm text-sm font-bold text-slate-600 hover:bg-slate-50 transition-colors">Next</a>
                    <?php
    endif; ?>
                </div>
            </div>
            <?php
endif; ?>

        </div>
        </div>

        <!-- Doctor Content -->
        <div id="content-doctors" class="tab-content hidden">
            <div class="bg-white rounded-3xl p-10 shadow-xl border border-slate-100">
                
                <div class="flex justify-between items-end mb-8 border-b border-slate-200 pb-6 flex-wrap gap-4">
                <div>
                    <h2 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Account Management</h2>
                    <p class="text-slate-500 font-medium">Manage user accounts and profiles.</p>
                </div>
                <div class="flex items-center gap-4 flex-wrap mt-4 sm:mt-0">
                    <form method="GET" action="admin.php" class="relative">
                        <input type="hidden" name="tab" value="doctors">
                        <input type="hidden" name="q_c" value="<?php echo htmlspecialchars($q_c); ?>">
                        <input type="hidden" name="c_page" value="<?php echo $c_page; ?>">
                        <input type="hidden" name="a_c_page" value="<?php echo $a_c_page; ?>">
                        <input type="hidden" name="a_d_page" value="<?php echo $a_d_page; ?>">
                        <input type="text" name="q_d" value="<?php echo htmlspecialchars($q_d); ?>" placeholder="Search accounts..." class="pl-10 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:emerald-500 focus:bg-white transition-all text-sm w-64">
                        <svg class="w-5 h-5 text-slate-400 absolute left-3 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </form>
                    <button id="add-doctor-btn" onclick="openAddDoctorModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold py-3.5 px-6 rounded-xl transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5 text-md flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Add New Account
                    </button>
                </div>
            </div>

            <div id="doc-save-msg" class="hidden mb-6 p-4 rounded-xl flex items-center gap-3 font-bold"></div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="doctors-table">
                    <thead>
                        <tr class="bg-slate-50 border-b-2 border-slate-200 text-slate-500 uppercase tracking-widest text-sm">
                            <th class="py-4 px-6 font-bold w-3/12">First Name</th>
                            <th class="py-4 px-6 font-bold w-3/12">Last Name</th>
                            <th class="py-4 px-6 font-bold w-2/12">Role</th>
                            <th class="py-4 px-6 font-bold w-2/12">Username</th>
                            <th class="py-4 px-6 font-bold w-3/12 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
// Join with users table to get the username and role
$users_query = "SELECT u.id, u.username, u.role, d.name, d.id as doctor_id FROM users u LEFT JOIN doctors d ON d.id = u.relation_id WHERE u.is_archived = 0 ORDER BY u.id ASC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($users_query);
$stmt->bind_param("ii", $limit, $d_offset);
$stmt->execute();
$users_res = $stmt->get_result();

if ($users_res->num_rows == 0):
?>
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-500 font-medium">No accounts found.</td>
                        </tr>
                        <?php
else:
    while ($u = $users_res->fetch_assoc()):
        $fullName = $u['name'] ?? '';
        // Split name for display/edit
        $clean_name = preg_replace('/^Dr\.\s+/i', '', $fullName);
        $parts = explode(' ', $clean_name, 2);
        $fName = $parts[0] ?? "";
        $lName = $parts[1] ?? "";
?>
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" 
                            data-user-id="<?php echo $u['id']; ?>" 
                            data-doctor-id="<?php echo $u['doctor_id'] ?? 0; ?>"
                            data-first-name="<?php echo htmlspecialchars($fName); ?>"
                            data-last-name="<?php echo htmlspecialchars($lName); ?>"
                            data-username="<?php echo htmlspecialchars($u['username']); ?>"
                            data-role="<?php echo htmlspecialchars($u['role']); ?>">
                            <td class="py-5 px-6 font-bold text-slate-700">
                                <?php echo htmlspecialchars($fName); ?>
                            </td>
                            <td class="py-5 px-6 font-bold text-slate-700">
                                <?php echo htmlspecialchars($lName); ?>
                            </td>
                            <td class="py-5 px-6">
                                <?php
        $role_class = 'bg-slate-100 text-slate-700';
        if ($u['role'] === 'receptionist')
            $role_class = 'bg-amber-100 text-amber-700';
        elseif ($u['role'] === 'doctor')
            $role_class = 'bg-blue-100 text-blue-700';
        elseif ($u['role'] === 'admin')
            $role_class = 'bg-rose-100 text-rose-700';
?>
                                <span class="px-3 py-1.5 rounded-full text-xs font-black uppercase tracking-widest <?php echo $role_class; ?>">
                                    <?php echo htmlspecialchars($u['role']); ?>
                                </span>
                            </td>
                            <td class="py-5 px-6 font-bold text-slate-700">
                                @<?php echo htmlspecialchars($u['username']); ?>
                            </td>
                            <td class="py-5 px-6 text-center">
                                <div class="flex justify-center gap-2">
                                    <button onclick="openEditDoctorModal(<?php echo $u['id']; ?>, <?php echo $u['doctor_id'] ?? 0; ?>, '<?php echo addslashes($fName); ?>', '<?php echo addslashes($lName); ?>', '<?php echo addslashes($u['username']); ?>', '<?php echo $u['role']; ?>')" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-2.5 px-6 rounded-lg transition-all shadow-sm">Edit</button>
                                    <button onclick="archiveDoctor(<?php echo $u['id']; ?>)" class="bg-red-50 hover:bg-red-100 text-red-600 font-bold py-2.5 px-4 rounded-lg transition-all shadow-sm" title="Archive Account" <?php echo($u['id'] == $_SESSION['id']) ? 'disabled' : ''; ?>>
                                        <svg class="w-5 h-5 block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php
    endwhile;
endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($d_pages > 1): ?>
            <div class="flex justify-between items-center mt-6">
                <div class="text-sm font-bold text-slate-500">
                    Showing page <?php echo $d_page; ?> of <?php echo $d_pages; ?>
                </div>
                <div class="flex gap-2">
                    <?php if ($d_page > 1): ?>
                    <a href="?tab=doctors&c_page=<?php echo $c_page; ?>&d_page=<?php echo $d_page - 1; ?>&a_c_page=<?php echo $a_c_page; ?>&a_d_page=<?php echo $a_d_page; ?>&q_c=<?php echo urlencode($q_c); ?>&q_d=<?php echo urlencode($q_d); ?>" class="px-4 py-2 border border-slate-200 rounded-lg shadow-sm text-sm font-bold text-slate-600 hover:bg-slate-50 transition-colors">Previous</a>
                    <?php
    endif; ?>
                    <?php if ($d_page < $d_pages): ?>
                    <a href="?tab=doctors&c_page=<?php echo $c_page; ?>&d_page=<?php echo $d_page + 1; ?>&a_c_page=<?php echo $a_c_page; ?>&a_d_page=<?php echo $a_d_page; ?>&q_c=<?php echo urlencode($q_c); ?>&q_d=<?php echo urlencode($q_d); ?>" class="px-4 py-2 border border-slate-200 rounded-lg shadow-sm text-sm font-bold text-slate-600 hover:bg-slate-50 transition-colors">Next</a>
                    <?php
    endif; ?>
                </div>
            </div>
            <?php
endif; ?>

        </div>
        </div>

        <!-- Archived Clinics Content -->
        <div id="content-archived-clinics" class="tab-content hidden">
            <div class="bg-white rounded-3xl p-10 shadow-xl border border-slate-100">
                <div class="mb-8 border-b border-slate-200 pb-6">
                    <h2 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Archived Clinics</h2>
                    <p class="text-slate-500 font-medium">Manage and restore previously archived clinics.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b-2 border-slate-200 text-slate-500 uppercase tracking-widest text-sm">
                                <th class="py-4 px-6 font-bold w-3/4">Clinic Name</th>
                                <th class="py-4 px-6 font-bold w-1/4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
$a_clins_query = "SELECT id, clinic_number FROM clinics WHERE is_archived = 1 ORDER BY LENGTH(clinic_number) ASC, clinic_number ASC LIMIT $limit OFFSET $a_c_offset";
$a_clins = $conn->query($a_clins_query);

if ($a_clins && $a_clins->num_rows == 0):
?>
                            <tr>
                                <td colspan="2" class="py-8 text-center text-slate-500 font-medium">No archived clinics found.</td>
                            </tr>
                            <?php
elseif ($a_clins):
    while ($c = $a_clins->fetch_assoc()): ?>
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors bg-slate-50" data-archived-clinic-id="<?php echo $c['id']; ?>">
                                <td class="py-5 px-6">
                                    <span class="font-extrabold text-xl text-slate-500 line-through"><?php echo htmlspecialchars($c['clinic_number']); ?></span>
                                </td>
                                <td class="py-5 px-6 text-center">
                                    <div class="flex justify-center gap-3">
                                        <button onclick="unarchiveClinic(<?php echo $c['id']; ?>)" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 font-bold py-2.5 px-6 rounded-lg transition-all shadow-sm flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                            Restore
                                        </button>
                                        <button onclick="deleteArchivedClinic(<?php echo $c['id']; ?>)" class="bg-red-100 hover:bg-red-200 text-red-700 font-bold py-2.5 px-6 rounded-lg transition-all shadow-sm flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php
    endwhile;
endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($a_c_pages > 1): ?>
                <div class="flex justify-between items-center mt-6">
                    <div class="text-sm font-bold text-slate-500">
                        Showing page <?php echo $a_c_page; ?> of <?php echo $a_c_pages; ?>
                    </div>
                    <div class="flex gap-2">
                        <?php if ($a_c_page > 1): ?>
                        <a href="?tab=archived-clinics&c_page=<?php echo $c_page; ?>&d_page=<?php echo $d_page; ?>&a_c_page=<?php echo $a_c_page - 1; ?>&a_d_page=<?php echo $a_d_page; ?>&q_c=<?php echo urlencode($q_c); ?>&q_d=<?php echo urlencode($q_d); ?>" class="px-4 py-2 border border-slate-200 rounded-lg shadow-sm text-sm font-bold text-slate-600 hover:bg-slate-50 transition-colors">Previous</a>
                        <?php
    endif; ?>
                        <?php if ($a_c_page < $a_c_pages): ?>
                        <a href="?tab=archived-clinics&c_page=<?php echo $c_page; ?>&d_page=<?php echo $d_page; ?>&a_c_page=<?php echo $a_c_page + 1; ?>&a_d_page=<?php echo $a_d_page; ?>&q_c=<?php echo urlencode($q_c); ?>&q_d=<?php echo urlencode($q_d); ?>" class="px-4 py-2 border border-slate-200 rounded-lg shadow-sm text-sm font-bold text-slate-600 hover:bg-slate-50 transition-colors">Next</a>
                        <?php
    endif; ?>
                    </div>
                </div>
                <?php
endif; ?>
            </div>
        </div>

        <!-- Archived Accounts Content -->
        <div id="content-archived-accs" class="tab-content hidden">
            <div class="bg-white rounded-3xl p-10 shadow-xl border border-slate-100">
                <div class="mb-8 border-b border-slate-200 pb-6">
                    <h2 class="text-4xl font-black text-slate-900 tracking-tight mb-2">Archived Accs</h2>
                    <p class="text-slate-500 font-medium">Manage and restore previously archived user accounts.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b-2 border-slate-200 text-slate-500 uppercase tracking-widest text-sm">
                                <th class="py-4 px-6 font-bold w-1/4">Account Name</th>
                                <th class="py-4 px-6 font-bold w-1/4">Username</th>
                                <th class="py-4 px-6 font-bold w-1/4">Role</th>
                                <th class="py-4 px-6 font-bold w-1/4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
$a_docs_query = "SELECT u.id, u.username, u.role, d.name FROM users u LEFT JOIN doctors d ON u.relation_id = d.id WHERE u.is_archived = 1 ORDER BY u.id ASC LIMIT $limit OFFSET $a_d_offset";
$a_docs = $conn->query($a_docs_query);

if ($a_docs && $a_docs->num_rows == 0):
?>
                            <tr>
                                <td colspan="4" class="py-8 text-center text-slate-500 font-medium">No archived accounts found.</td>
                            </tr>
                            <?php
elseif ($a_docs):
    while ($u = $a_docs->fetch_assoc()):
        $displayName = $u['name'] ? $u['name'] : 'N/A';
?>
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors bg-slate-50" data-archived-user-id="<?php echo $u['id']; ?>">
                                <td class="py-5 px-6">
                                    <span class="font-extrabold text-xl text-slate-500 line-through"><?php echo htmlspecialchars($displayName); ?></span>
                                </td>
                                <td class="py-5 px-6">
                                    <span class="font-bold text-slate-400">@<?php echo htmlspecialchars($u['username']); ?></span>
                                </td>
                                <td class="py-5 px-6">
                                    <span class="px-3 py-1 bg-slate-100 text-slate-500 rounded-full text-xs font-black uppercase tracking-widest">
                                        <?php echo htmlspecialchars($u['role']); ?>
                                    </span>
                                </td>
                                <td class="py-5 px-6 text-center">
                                    <div class="flex justify-center gap-3">
                                        <button onclick="unarchiveAccount(<?php echo $u['id']; ?>)" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 font-bold py-2.5 px-6 rounded-lg transition-all shadow-sm flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                                            Restore
                                        </button>
                                        <button onclick="deleteArchivedAccount(<?php echo $u['id']; ?>)" class="bg-red-100 hover:bg-red-200 text-red-700 font-bold py-2.5 px-6 rounded-lg transition-all shadow-sm flex items-center gap-2">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php
    endwhile;
endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($a_d_pages > 1): ?>
                <div class="flex justify-between items-center mt-6">
                    <div class="text-sm font-bold text-slate-500">
                        Showing page <?php echo $a_d_page; ?> of <?php echo $a_d_pages; ?>
                    </div>
                    <div class="flex gap-2">
                        <?php if ($a_d_page > 1): ?>
                        <a href="?tab=archived-accs&c_page=<?php echo $c_page; ?>&d_page=<?php echo $d_page; ?>&a_c_page=<?php echo $a_c_page; ?>&a_d_page=<?php echo $a_d_page - 1; ?>&q_c=<?php echo urlencode($q_c); ?>&q_d=<?php echo urlencode($q_d); ?>" class="px-4 py-2 border border-slate-200 rounded-lg shadow-sm text-sm font-bold text-slate-600 hover:bg-slate-50 transition-colors">Previous</a>
                        <?php
    endif; ?>
                        <?php if ($a_d_page < $a_d_pages): ?>
                        <a href="?tab=archived-accs&c_page=<?php echo $c_page; ?>&d_page=<?php echo $d_page; ?>&a_c_page=<?php echo $a_c_page; ?>&a_d_page=<?php echo $a_d_page + 1; ?>&q_c=<?php echo urlencode($q_c); ?>&q_d=<?php echo urlencode($q_d); ?>" class="px-4 py-2 border border-slate-200 rounded-lg shadow-sm text-sm font-bold text-slate-600 hover:bg-slate-50 transition-colors">Next</a>
                        <?php
    endif; ?>
                    </div>
                </div>
                <?php
endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Clinic Modal Popup -->
    <div id="add-clinic-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Dark Background Overlay -->
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeAddClinicModal()"></div>
        
        <!-- Modal Panel -->
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 relative z-10 transform scale-100 transition-all font-sans">
            <h3 class="text-2xl font-black text-slate-900 mb-2">Add New Clinic</h3>
            <p class="text-slate-500 mb-6 font-medium">Please enter a manual name or identifier for the new physical clinic location.</p>
            
            <div class="mb-8">
                <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">Clinic Identifier</label>
                <input type="text" id="new-clinic-name" placeholder="e.g. Clinic 4 or Dental Wing" class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all font-bold text-lg text-slate-800 placeholder:text-slate-300 placeholder:font-medium">
            </div>
            
            <div class="flex gap-3 justify-end">
                <button onclick="closeAddClinicModal()" class="px-6 py-3 font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Cancel</button>
                <button id="confirm-add-btn" onclick="confirmAddClinic()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">Create Clinic</button>
            </div>
        </div>
    </div>

    <!-- Add Doctor Modal Popup -->
    <div id="add-doctor-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Dark Background Overlay -->
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeAddDoctorModal()"></div>
        
        <!-- Modal Panel -->
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 relative z-10 transform scale-100 transition-all font-sans">
            <h3 class="text-2xl font-black text-slate-900 mb-2">Register New Account</h3>
            <p class="text-slate-500 mb-6 font-medium">Create a new user profile for doctors or receptionists.</p>
            
            <div class="mb-4">
                <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">Account Type / Role</label>
                <select id="new-doctor-role" class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:emerald-500/20 focus:border-emerald-500 focus:bg-white transition-all font-bold text-lg text-slate-800">
                    <option value="doctor">Doctor</option>
                    <option value="receptionist">Receptionist</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">First Name</label>
                    <input type="text" id="new-doctor-first-name" placeholder="Sarah" class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:emerald-500/20 focus:border-emerald-500 focus:bg-white transition-all font-bold text-lg text-slate-800 placeholder:text-slate-300 placeholder:font-medium">
                </div>
                <div>
                    <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">Last Name</label>
                    <input type="text" id="new-doctor-last-name" placeholder="Jenkins" class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:emerald-500/20 focus:border-emerald-500 focus:bg-white transition-all font-bold text-lg text-slate-800 placeholder:text-slate-300 placeholder:font-medium">
                </div>
            </div>

            <div class="mb-4">
                <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">Username</label>
                <input type="text" id="new-doctor-username" placeholder="e.g. dr_jenkins" class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:emerald-500/20 focus:border-emerald-500 focus:bg-white transition-all font-bold text-lg text-slate-800 placeholder:text-slate-300 placeholder:font-medium">
            </div>

            <div class="mb-8">
                <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">Temporary Password</label>
                <input type="password" id="new-doctor-password" placeholder="••••••••" class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:emerald-500/20 focus:border-emerald-500 focus:bg-white transition-all font-bold text-lg text-slate-800 placeholder:text-slate-300 placeholder:font-medium">
            </div>
            
            <div class="flex gap-3 justify-end">
                <button onclick="closeAddDoctorModal()" class="px-6 py-3 font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Cancel</button>
                <button id="confirm-add-doc-btn" onclick="confirmAddDoctor()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">Register</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Doctor Modal Popup -->
    <div id="edit-doctor-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Dark Background Overlay -->
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeEditDoctorModal()"></div>
        
        <!-- Modal Panel -->
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 relative z-10 transform scale-100 transition-all font-sans">
            <div class="flex items-center gap-3 mb-2">
                <div class="p-2 bg-emerald-50 rounded-lg text-emerald-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                </div>
                <h3 class="text-2xl font-black text-slate-900">Edit Account</h3>
            </div>
            <p class="text-slate-500 mb-6 font-medium">Update account profile details and security.</p>
            
            <input type="hidden" id="edit-user-id">
            <input type="hidden" id="edit-doctor-id">
            
            <div class="mb-4">
                <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">Account Type / Role</label>
                <select id="edit-doctor-role" class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:emerald-500/20 focus:border-emerald-500 focus:bg-white transition-all font-bold text-lg text-slate-800">
                    <option value="doctor">Doctor</option>
                    <option value="receptionist">Receptionist</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">First Name</label>
                    <input type="text" id="edit-doctor-first-name" class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:emerald-500/20 focus:border-emerald-500 focus:bg-white transition-all font-bold text-lg text-slate-800">
                </div>
                <div>
                    <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">Last Name</label>
                    <input type="text" id="edit-doctor-last-name" class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:emerald-500/20 focus:border-emerald-500 focus:bg-white transition-all font-bold text-lg text-slate-800">
                </div>
            </div>

            <div class="mb-4">
                <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">Username</label>
                <input type="text" id="edit-doctor-username" class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:emerald-500/20 focus:border-emerald-500 focus:bg-white transition-all font-bold text-lg text-slate-800">
            </div>

            <div class="mb-8">
                <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-xs">New Password <span class="text-slate-400 font-medium normal-case">(Blank to keep current)</span></label>
                <input type="password" id="edit-doctor-password" placeholder="••••••••" class="w-full px-5 py-4 bg-slate-50 border-2 border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:emerald-500/20 focus:border-emerald-500 focus:bg-white transition-all font-bold text-lg text-slate-800">
            </div>
            
            <div class="flex gap-3 justify-end">
                <button onclick="closeEditDoctorModal()" class="px-6 py-3 font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Cancel</button>
                <button id="confirm-update-doc-btn" onclick="confirmUpdateDoctor()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            switchTab('<?php echo htmlspecialchars($active_tab); ?>');
        });

        // Restrict input to numbers only and max 255. Also auto-jump to next box.
        bindIPValidation(document.querySelectorAll('.ip-octet'));

        function enableEdit(clinicId) {
            const row = document.querySelector(`tr[data-clinic-id="${clinicId}"]`);
            if (!row) return;

            // Enable inputs
            row.querySelectorAll('.ip-octet').forEach(input => input.disabled = false);
            
            // Focus the first input
            row.querySelector('.ip-octet').focus();

            // Toggle buttons
            document.getElementById(`edit-btn-${clinicId}`).classList.add('hidden');
            document.getElementById(`save-btn-${clinicId}`).classList.remove('hidden');
            document.getElementById(`save-btn-${clinicId}`).classList.add('flex'); // to use gap-2 if needed
        }

        async function saveClinicIP(clinicId) {
            const row = document.querySelector(`tr[data-clinic-id="${clinicId}"]`);
            if (!row) return;

            const btn = document.getElementById(`save-btn-${clinicId}`);
            const originalText = btn.innerHTML;
            btn.innerHTML = 'Saving...';
            btn.classList.add('opacity-75', 'cursor-not-allowed');

            const octets = Array.from(row.querySelectorAll('.ip-octet')).map(i => i.value);
            const filledCount = octets.filter(o => o.trim() !== '').length;
            
            let ipString = null;

            if (filledCount === 4) {
                ipString = octets.join('.');
            } else if (filledCount > 0 && filledCount < 4) {
                // Incomplete IP
                row.querySelector('.ip-group').classList.add('ring-4', 'ring-red-200', 'rounded-xl');
                setTimeout(() => row.querySelector('.ip-group').classList.remove('ring-4', 'ring-red-200'), 3000);
                showMessage("IP address is incomplete. Please fill all 4 boxes or clear them entirely.", false);
                btn.innerHTML = originalText;
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
                return;
            }

            const payload = [{ id: clinicId, ip: ipString }];

            try {
                const res = await fetch('api.php?action=update_ips', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                
                const data = await res.json();
                
                if (data.success) {
                    showMessage("✔ IP Address successfully updated for clinic.", true);
                    
                    // Disable inputs and toggle buttons back
                    row.querySelectorAll('.ip-octet').forEach(input => input.disabled = true);
                    document.getElementById(`save-btn-${clinicId}`).classList.add('hidden');
                    document.getElementById(`save-btn-${clinicId}`).classList.remove('flex');
                    document.getElementById(`edit-btn-${clinicId}`).classList.remove('hidden');
                } else {
                    showMessage("System Error: " + data.error, false);
                }
            } catch (err) {
                showMessage("Connection Error: " + err.message, false);
            }

            btn.innerHTML = originalText;
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
        }

        function showMessage(text, isSuccess) {
            const msg = document.getElementById('save-msg');
            msg.classList.remove('hidden', 'bg-emerald-100', 'text-emerald-800', 'bg-red-100', 'text-red-800');
            if (isSuccess) {
                msg.classList.add('bg-emerald-100', 'text-emerald-800');
            } else {
                msg.classList.add('bg-red-100', 'text-red-800');
            }
            msg.innerText = text;
            
            // Auto hide after 4 seconds
            setTimeout(() => {
                msg.classList.add('hidden');
            }, 4000);
        }

        function openAddClinicModal() {
            document.getElementById('new-clinic-name').value = '';
            document.getElementById('add-clinic-modal').classList.remove('hidden');
            setTimeout(() => document.getElementById('new-clinic-name').focus(), 100);
        }

        function closeAddClinicModal() {
            document.getElementById('add-clinic-modal').classList.add('hidden');
        }

        async function confirmAddClinic() {
            const input = document.getElementById('new-clinic-name');
            const clinicName = input.value.trim();
            
            if (!clinicName) {
                input.classList.add('ring-4', 'ring-red-200');
                setTimeout(() => input.classList.remove('ring-4', 'ring-red-200'), 2000);
                return;
            }

            const btn = document.getElementById('confirm-add-btn');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = 'Creating...';
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');

            try {
                const res = await fetch('api.php?action=create_clinic', { 
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ clinic_name: clinicName })
                });
                const data = await res.json();

                if (data.success) {
                    closeAddClinicModal();
                    showMessage("✔ Successfully added " + data.name + " to the network.", true);
                    
                    // Dynamically build and append the new row
                    const tbody = document.querySelector('tbody');
                    const tr = document.createElement('tr');
                    tr.className = 'border-b border-slate-100 hover:bg-slate-50 transition-colors bg-blue-50/30';
                    tr.setAttribute('data-clinic-id', data.id);
                    
                    tr.innerHTML = `
                        <td class="py-5 px-6">
                            <span class="font-extrabold text-xl text-slate-800">${data.name}</span>
                        </td>
                        <td class="py-5 px-6">
                            <div class="flex items-end gap-2 ip-group">
                                <input type="number" min="0" max="255" class="ip-octet w-20 px-3 py-3 bg-slate-100 border-2 border-slate-200 rounded-xl text-center font-bold text-xl text-blue-800 disabled:opacity-60 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed focus:outline-none focus:border-blue-500 focus:bg-white transition-all placeholder:text-slate-300" placeholder="192" value="" disabled>
                                <span class="text-3xl font-black text-slate-300 pb-2">.</span>
                                <input type="number" min="0" max="255" class="ip-octet w-20 px-3 py-3 bg-slate-100 border-2 border-slate-200 rounded-xl text-center font-bold text-xl text-blue-800 disabled:opacity-60 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed focus:outline-none focus:border-blue-500 focus:bg-white transition-all placeholder:text-slate-300" placeholder="168" value="" disabled>
                                <span class="text-3xl font-black text-slate-300 pb-2">.</span>
                                <input type="number" min="0" max="255" class="ip-octet w-20 px-3 py-3 bg-slate-100 border-2 border-slate-200 rounded-xl text-center font-bold text-xl text-blue-800 disabled:opacity-60 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed focus:outline-none focus:border-blue-500 focus:bg-white transition-all placeholder:text-slate-300" placeholder="1" value="" disabled>
                                <span class="text-3xl font-black text-slate-300 pb-2">.</span>
                                <input type="number" min="0" max="255" class="ip-octet w-20 px-3 py-3 bg-slate-100 border-2 border-slate-200 rounded-xl text-center font-bold text-xl text-blue-800 disabled:opacity-60 disabled:bg-slate-50 disabled:text-slate-500 disabled:cursor-not-allowed focus:outline-none focus:border-blue-500 focus:bg-white transition-all placeholder:text-slate-300" placeholder="100" value="" disabled>
                            </div>
                        </td>
                        <td class="py-5 px-6 text-center">
                            <button onclick="enableEdit(${data.id})" id="edit-btn-${data.id}" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold py-2.5 px-6 rounded-lg transition-all shadow-sm">Edit</button>
                            <button onclick="saveClinicIP(${data.id})" id="save-btn-${data.id}" class="hidden bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-6 rounded-lg transition-all shadow-md">Save</button>
                        </td>
                    `;
                    
                    tbody.appendChild(tr);
                    
                    // Briefly highlight the new row to show it was added
                    setTimeout(() => tr.classList.remove('bg-blue-50/30'), 2000);
                    
                    // Re-bind input validation events for the newly added inputs
                    bindIPValidation(tr.querySelectorAll('.ip-octet'));

                } else {
                    closeAddClinicModal();
                    showMessage("System Error: " + data.error, false);
                }
            } catch (err) {
                closeAddClinicModal();
                showMessage("Connection Error: " + err.message, false);
            }

            btn.innerHTML = originalHTML;
            btn.disabled = false;
            btn.classList.remove('opacity-75', 'cursor-not-allowed');
        }

        function openAddDoctorModal() {
            document.getElementById('new-doctor-first-name').value = '';
            document.getElementById('new-doctor-last-name').value = '';
            document.getElementById('new-doctor-username').value = '';
            document.getElementById('new-doctor-password').value = '';
            document.getElementById('add-doctor-modal').classList.remove('hidden');
            setTimeout(() => document.getElementById('new-doctor-first-name').focus(), 100);
        }

        function closeAddDoctorModal() {
            document.getElementById('add-doctor-modal').classList.add('hidden');
        }

        async function confirmAddDoctor() {
            const fNameInput = document.getElementById('new-doctor-first-name');
            const lNameInput = document.getElementById('new-doctor-last-name');
            const userInput = document.getElementById('new-doctor-username');
            const passInput = document.getElementById('new-doctor-password');
            const roleInput = document.getElementById('new-doctor-role');
            
            const firstName = fNameInput.value.trim();
            const lastName = lNameInput.value.trim();
            const username = userInput.value.trim();
            const password = passInput.value;
            const role = roleInput.value;
            
            let hasError = false;
            if (!firstName) { fNameInput.classList.add('ring-4', 'ring-red-200'); setTimeout(() => fNameInput.classList.remove('ring-4', 'ring-red-200'), 2000); hasError = true; }
            if (!lastName) { lNameInput.classList.add('ring-4', 'ring-red-200'); setTimeout(() => lNameInput.classList.remove('ring-4', 'ring-red-200'), 2000); hasError = true; }
            if (!username) { userInput.classList.add('ring-4', 'ring-red-200'); setTimeout(() => userInput.classList.remove('ring-4', 'ring-red-200'), 2000); hasError = true; }
            if (!password) { passInput.classList.add('ring-4', 'ring-red-200'); setTimeout(() => passInput.classList.remove('ring-4', 'ring-red-200'), 2000); hasError = true; }
            if (hasError) return;

            const btn = document.getElementById('confirm-add-doc-btn');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = 'Registering...';
            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');

            try {
                const res = await fetch('api.php?action=create_doctor', { 
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        firstName: firstName, 
                        lastName: lastName, 
                        username: username, 
                        password: password, 
                        role: role 
                    })
                });
                const data = await res.json();

                if (data.success) {
                    closeAddDoctorModal();
                    window.location.reload();
                } else {
                    alert("Error: " + data.error);
                }
            } catch (err) {
                alert("Connection Error");
            } finally {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                btn.classList.remove('opacity-75', 'cursor-not-allowed');
            }
        }


        // Helper function for the IP validation logic since dynamically added rows need it too
        function bindIPValidation(inputsNodeList) {
            inputsNodeList.forEach((input, index, inputs) => {
                input.addEventListener('keydown', (e) => {
                    if (['e', 'E', '+', '-', '.'].includes(e.key)) {
                        e.preventDefault();
                    }
                    if (e.key === 'Backspace' && input.value === '' && index > 0) {
                        inputs[index - 1].focus();
                    }
                });

                input.addEventListener('input', (e) => {
                    let val = parseInt(e.target.value);
                    if (val > 255) e.target.value = 255;
                    if (val < 0) e.target.value = 0;

                    if (e.target.value.length === 3 && index < inputs.length - 1) {
                        const group = input.closest('.ip-group');
                        const nextInputInGroup = group.querySelectorAll('.ip-octet')[Array.from(group.querySelectorAll('.ip-octet')).indexOf(input) + 1];
                        if (nextInputInGroup) {
                            nextInputInGroup.focus();
                        }
                    }
                });
            });
        }

        function switchTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.add('hidden');
                el.classList.remove('block');
            });
            
            // Show selected tab content
            document.getElementById('content-' + tabId).classList.remove('hidden');
            document.getElementById('content-' + tabId).classList.add('block');
            
            // Reset tab button styling
            const colorMap = {
                'clinics': 'blue',
                'doctors': 'emerald',
                'archived-clinics': 'purple',
                'archived-accs': 'rose'
            };

            document.querySelectorAll('.tab-btn').forEach(btn => {
                const id = btn.id.replace('tab-', '');
                if (id === tabId) {
                    const color = colorMap[id] || 'blue';
                    btn.className = `tab-btn pb-4 px-4 text-lg font-bold border-b-4 border-${color}-600 text-${color}-600 transition-colors whitespace-nowrap`;
                } else {
                    btn.className = "tab-btn pb-4 px-4 text-lg font-bold border-b-4 border-transparent text-slate-500 hover:text-slate-700 transition-colors whitespace-nowrap";
                }
            });
        }

        function togglePasswordVisibility(btn) {
            const container = btn.closest('.doctor-password-container');
            const input = container.querySelector('.doctor-password-input');
            const eyeIcon = container.querySelector('.eye-icon');
            const eyeSlashIcon = container.querySelector('.eye-slash-icon');

            if (input.type === 'password') {
                input.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeSlashIcon.classList.remove('hidden');
            } else {
                input.type = 'password';
                eyeSlashIcon.classList.add('hidden');
                eyeIcon.classList.remove('hidden');
            }
        }

        function openEditDoctorModal(userId, docId, fName, lName, username, role) {
            document.getElementById('edit-user-id').value = userId;
            document.getElementById('edit-doctor-id').value = docId;
            document.getElementById('edit-doctor-first-name').value = fName;
            document.getElementById('edit-doctor-last-name').value = lName;
            document.getElementById('edit-doctor-username').value = username;
            document.getElementById('edit-doctor-role').value = role;
            document.getElementById('edit-doctor-password').value = '';
            
            document.getElementById('edit-doctor-modal').classList.remove('hidden');
            setTimeout(() => document.getElementById('edit-doctor-first-name').focus(), 100);
        }

        function closeEditDoctorModal() {
            document.getElementById('edit-doctor-modal').classList.add('hidden');
        }

        async function confirmUpdateDoctor() {
            const userId = document.getElementById('edit-user-id').value;
            const docId = document.getElementById('edit-doctor-id').value;
            const fNameInput = document.getElementById('edit-doctor-first-name');
            const lNameInput = document.getElementById('edit-doctor-last-name');
            const userInput = document.getElementById('edit-doctor-username');
            const passInput = document.getElementById('edit-doctor-password');
            
            const firstName = fNameInput.value.trim();
            const lastName = lNameInput.value.trim();
            const username = userInput.value.trim();
            const role = document.getElementById('edit-doctor-role').value;
            const password = passInput.value;
            
            let hasError = false;
            if (!firstName) { fNameInput.classList.add('ring-4', 'ring-red-200'); setTimeout(() => fNameInput.classList.remove('ring-4', 'ring-red-200'), 2000); hasError = true; }
            if (!lastName) { lNameInput.classList.add('ring-4', 'ring-red-200'); setTimeout(() => lNameInput.classList.remove('ring-4', 'ring-red-200'), 2000); hasError = true; }
            if (!username) { userInput.classList.add('ring-4', 'ring-red-200'); setTimeout(() => userInput.classList.remove('ring-4', 'ring-red-200'), 2000); hasError = true; }
            if (hasError) return;

            const btn = document.getElementById('confirm-update-doc-btn');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = 'Saving Changes...';
            btn.disabled = true;

            try {
                const res = await fetch('api.php?action=update_doctor', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ 
                        id: docId, 
                        userId: userId,
                        firstName: firstName, 
                        lastName: lastName, 
                        username: username, 
                        role: role,
                        password: password,
                        skip_password_check: true
                    })
                });
                const data = await res.json();

                if (data.success) {
                    window.location.reload();
                } else {
                    alert("Error: " + data.error);
                }
            } catch (err) {
                alert("Connection Error");
            } finally {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        }

        async function archiveClinic(id) {
            if(!confirm("Are you sure you want to archive this clinic? It will be removed from all future selections.")) return;
            
            try {
                const res = await fetch('api.php?action=archive_clinic', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert("Error: " + data.error);
                }
            } catch(e) {
                alert("Connection error.");
            }
        }

        async function archiveDoctor(id) {
            if(!confirm("Are you sure you want to archive this account? They will lose access to the system.")) return;
            
            try {
                const res = await fetch('api.php?action=archive_doctor', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert("Error: " + data.error);
                }
            } catch(e) {
                alert("Connection error.");
            }
        }

        async function unarchiveClinic(id) {
            if(!confirm("Are you sure you want to restore this clinic?")) return;
            
            try {
                const res = await fetch('api.php?action=unarchive_clinic', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert("Error: " + data.error);
                }
            } catch(e) {
                alert("Connection error.");
            }
        }

        async function unarchiveAccount(id) {
            if(!confirm("Are you sure you want to restore this account? You will need to re-configure their credentials after restoring.")) return;
            
            try {
                const res = await fetch('api.php?action=unarchive_account', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert("Error: " + data.error);
                }
            } catch(e) {
                alert("Connection error.");
            }
        }

        async function deleteArchivedClinic(id) {
            if(!confirm("WARNING: This will permanently delete this clinic from the database. This action CANNOT be undone. Are you absolutely sure?")) return;
            
            try {
                const res = await fetch('api.php?action=delete_clinic', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert("Error: " + data.error);
                }
            } catch(e) {
                alert("Connection error.");
            }
        }

        async function deleteArchivedAccount(id) {
            if(!confirm("WARNING: This will permanently delete this user account and its associated records. This action CANNOT be undone. Are you absolutely sure?")) return;
            
            try {
                const res = await fetch('api.php?action=delete_account', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert("Error: " + data.error);
                }
            } catch(e) {
                alert("Connection error.");
            }
        }
    </script>
</body>
</html>
