<?php 
require 'db.php'; 
require 'auth.php';
requireRole(['receptionist']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased min-h-screen flex flex-col items-center justify-center p-6 relative">
    
    <!-- Top Navigation -->
    <a href="index.php" class="absolute top-6 left-6 font-bold text-slate-600 hover:text-blue-600 text-sm tracking-wider uppercase bg-white shadow-sm px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Back
    </a>
    <div class="absolute top-6 right-6 flex items-center gap-3">
        <span class="text-sm font-bold text-slate-500 uppercase tracking-widest"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        <a href="logout.php" class="font-bold text-red-600 hover:text-red-800 text-sm tracking-wider uppercase bg-red-50 px-4 py-2 rounded-lg transition-colors">Logout</a>
    </div>

    <div class="w-full max-w-lg mt-12">
        <div class="bg-white rounded-3xl p-10 shadow-xl border border-slate-100">
            <h2 class="text-3xl font-extrabold text-slate-900 mb-8 text-center pt-2">Patient Registration</h2>
            <form id="registrationForm" class="space-y-6">

                <div>
                    <label for="doctor_id" class="block mb-2 font-bold text-slate-700 tracking-tight text-sm uppercase">Assign Doctor</label>
                    <select id="doctor_id" name="doctor_id" required class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-xl font-medium focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all appearance-none cursor-pointer">
                        <option value="">Select a Doctor</option>
                        <?php
                        if (!$conn->connect_error) {
                            $val = $conn->query("SHOW TABLES LIKE 'doctors'");
                            if($val && $val->num_rows > 0) {
                                $docs = $conn->query("SELECT d.* FROM doctors d 
                                                     JOIN users u ON u.relation_id = d.id 
                                                     WHERE u.role = 'doctor' 
                                                     AND d.is_archived = 0 
                                                     AND u.is_archived = 0");
                                while($d = $docs->fetch_assoc()) {
                                    $selected = (isset($_SESSION['doctor_id']) && $_SESSION['doctor_id'] == $d['id']) ? 'selected' : '';
                                    echo "<option value='{$d['id']}' $selected>{$d['name']}</option>";
                                }
                            }
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-2 font-bold text-slate-700 tracking-tight text-sm uppercase">Active Clinic Location</label>
                    <div id="clinic_display" class="w-full px-5 py-4 bg-slate-100 border-2 border-slate-200 rounded-xl font-black text-blue-600 flex items-center justify-between transition-all">
                        <span id="clinic_name_text">Detecting Clinic...</span>
                        <div id="clinic_status_indicator" class="w-2.5 h-2.5 rounded-full bg-slate-300"></div>
                    </div>
                    <input type="hidden" id="clinic_id" name="clinic_id" required>
                    <p id="clinic_helper" class="mt-2 text-xs font-bold text-slate-400 uppercase tracking-wider">The clinic is automatically set based on where the doctor is logged in.</p>
                </div>
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-lg py-4 rounded-xl transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5 mt-4">Register Patient</button>
            </form>
            <div id="reg-result" class="hidden mt-8"></div>
        </div>
    </div>
    <script>
        const terminalClinicId = <?php echo json_encode($_SESSION['relation_id'] ?? null); ?>;
        const terminalClinicName = <?php 
            if (isset($_SESSION['relation_id'])) {
                $rid = intval($_SESSION['relation_id']);
                $v = $conn->query("SELECT clinic_number FROM clinics WHERE id = $rid");
                echo json_encode($v->fetch_assoc()['clinic_number']);
            } else { echo 'null'; }
        ?>;
    </script>
    <script src="script.js"></script>
</body>
</html>
