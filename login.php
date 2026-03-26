<?php
require 'config/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

// 1. Detect physical terminal IP
$client_ip = $_SERVER['REMOTE_ADDR'];
if ($client_ip === '::1') {
    $client_ip = '127.0.0.1'; // Normalize local IPv6 loopback to IPv4 for easier local testing
}

// 2. Check if this IP is registered to a clinic
$stmt_ip = $conn->prepare("SELECT id, clinic_number FROM clinics WHERE ip_address = ?");
$stmt_ip->bind_param("s", $client_ip);
$stmt_ip->execute();
$res_ip = $stmt_ip->get_result();
$terminal_clinic = $res_ip->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $stmt = $conn->prepare("SELECT id, username, password, role, relation_id FROM users WHERE username = ? AND is_archived = 0");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            
            // Assign clinic relation_id if terminal is detected (for all roles)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['doctor_id'] = $user['relation_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['relation_id'] = $terminal_clinic ? $terminal_clinic['id'] : null;

            // If they are a doctor, Enforce IP matching and record their location
            if ($user['role'] === 'doctor') {
                if ($terminal_clinic) {
                    $doc_id = intval($user['relation_id']);
                    $clin_id = intval($terminal_clinic['id']);
                    $conn->query("UPDATE doctors SET current_clinic_id = $clin_id WHERE id = $doc_id");
                    header("Location: doctor.php");
                    exit;
                } else {
                    $error = "Doctor Access Denied: This terminal ($client_ip) is not assigned to a clinic.";
                    // Clean up session since they can't log in
                    session_destroy();
                }
            } else {
                if ($user['role'] === 'receptionist') {
                    header("Location: registration.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Hospital Queue</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md bg-white rounded-3xl p-10 shadow-xl border border-slate-100">
        <div class="text-center mb-10">
            <h1 class="text-3xl font-black text-blue-600 tracking-tight mb-2">Hospital Queuing</h1>
            <p class="text-slate-500 font-medium mb-4">Please sign in to continue</p>
            
            <div class="inline-flex items-center gap-2 bg-slate-100 px-4 py-2 rounded-lg border border-slate-200">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                <span class="text-xs font-bold text-slate-600 tracking-wider">
                    IP: <?php echo htmlspecialchars($client_ip); ?>
                    &bull;
                    <span class="<?php echo $terminal_clinic ? 'text-emerald-600' : 'text-slate-400'; ?>">
                        <?php echo $terminal_clinic ? htmlspecialchars($terminal_clinic['clinic_number']) : 'No Clinic Assigned'; ?>
                    </span>
                </span>
            </div>
        </div>
        
        <?php if($error): ?>
            <div class="mb-6 p-4 bg-red-50 text-red-700 border border-red-200 rounded-xl font-bold text-center">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-sm">Username</label>
                <input type="text" name="username" required class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all font-medium">
            </div>
            <div>
                <label class="block mb-2 font-bold text-slate-700 uppercase tracking-tight text-sm">Password</label>
                <input type="password" name="password" required class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-4 focus:ring-blue-500/20 focus:border-blue-500 focus:bg-white transition-all font-medium">
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-extrabold py-4 rounded-xl transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5 text-lg">Sign In</button>
        </form>
        
        <div class="mt-8 text-center text-slate-500 text-sm">
            <p><strong>Demo Accounts:</strong> (password: <code class="bg-slate-100 px-2 py-1 rounded">1234</code>)</p>
            <p>admin, receptionist, doctor1, doctor2</p>
        </div>
        <div class="mt-4 text-center">
            <a href="index.php" class="text-blue-600 font-medium hover:underline">← Back to Public Dashboard</a>
        </div>
    </div>
</body>
</html>
