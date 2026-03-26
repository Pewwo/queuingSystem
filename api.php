<?php
require 'config/db.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action == 'dashboard') {
    // get dashboard data
    $sql = "SELECT c.clinic_number, cs.status, q.queue_number, q.patient_name, d.name as doctor_name,
            (SELECT COUNT(*) FROM queues 
             WHERE status = 'waiting' 
             AND (clinic_id = c.id OR (clinic_id IS NULL AND doctor_id = d.id))
            ) as waiting_count,
            (SELECT GROUP_CONCAT(queue_number ORDER BY id ASC) FROM queues 
             WHERE status = 'waiting' 
             AND (clinic_id = c.id OR (clinic_id IS NULL AND doctor_id = d.id))
            ) as waiting_list
            FROM clinic_status cs
            JOIN clinics c ON cs.clinic_id = c.id
            LEFT JOIN queues q ON cs.current_queue_id = q.id
            LEFT JOIN doctors d ON d.current_clinic_id = c.id
            WHERE c.is_archived = 0
            ORDER BY LENGTH(c.clinic_number) ASC, c.clinic_number ASC";
    $res = $conn->query($sql);
    $data = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
    }
    echo json_encode($data);
    exit;
}

if ($action == 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        $doctor_id = $_POST['doctor_id'] ?? 0;
        $clinic_id = $_POST['clinic_id'] ?? 0;
    } else {
        $doctor_id = $data['doctor_id'] ?? 0;
        $clinic_id = $data['clinic_id'] ?? 0;
    }

    // Server-side check for archives
    $check_stmt = $conn->prepare("SELECT (SELECT is_archived FROM doctors WHERE id = ?) as d_archived, (SELECT is_archived FROM clinics WHERE id = ?) as c_archived");
    $check_stmt->bind_param("ii", $doctor_id, $clinic_id);
    $check_stmt->execute();
    $states = $check_stmt->get_result()->fetch_assoc();
    if($states['d_archived'] == 1 || $states['c_archived'] == 1) {
        echo json_encode(['success' => false, 'error' => 'The selected doctor or clinic is currently archived.']);
        exit;
    }
    
    // Default patient name since it's removed from UI
    $patient_name = "Walk-in Patient";
    
    // get next queue number for TODAY and this SPECIFIC DOCTOR
    $sql_qn = "SELECT MAX(queue_number) as max_q FROM queues WHERE doctor_id = ? AND DATE(created_at) = CURDATE()";
    $stmt_qn = $conn->prepare($sql_qn);
    $stmt_qn->bind_param("i", $doctor_id);
    $stmt_qn->execute();
    $res = $stmt_qn->get_result();
    $row = $res->fetch_assoc();
    $next_q = ($row['max_q'] ? $row['max_q'] : 0) + 1;
    
    // Convert 0 to null for DB
    $db_clinic_id = ($clinic_id > 0) ? $clinic_id : null;  
    
    $stmt = $conn->prepare("INSERT INTO queues (queue_number, patient_name, doctor_id, clinic_id, status) VALUES (?, ?, ?, ?, 'waiting')");
    // Use 's' for clinic_id if it can be NULL (which is a string type for bind_param)
    $stmt->bind_param("isis", $next_q, $patient_name, $doctor_id, $db_clinic_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'queue_number' => $next_q]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

if ($action == 'doctor_state') {
    $clinic_id = $_GET['clinic_id'] ?? 0;
    $sql = "SELECT c.clinic_number, cs.status as clinic_status, q.id as queue_id, q.patient_name, q.queue_number,
            (SELECT COUNT(*) FROM queues WHERE clinic_id = cs.clinic_id AND status = 'waiting') as waiting_count
            FROM clinic_status cs
            JOIN clinics c ON cs.clinic_id = c.id
            LEFT JOIN queues q ON cs.current_queue_id = q.id
            WHERE cs.clinic_id = " . intval($clinic_id);
    $res = $conn->query($sql);
    echo json_encode($res ? $res->fetch_assoc() : []);
    exit;
}

if ($action == 'call_next') {
    $data = json_decode(file_get_contents('php://input'), true);
    $clinic_id = $data['clinic_id'] ?? 0;
    
    // mark current as done
    $conn->query("UPDATE queues SET status = 'done' WHERE clinic_id = $clinic_id AND status = 'serving'");
    
    // get next waiting patient for THIS CLINIC or THIS DOCTOR (if clinic is null)
    // We prioritize patients specifically assigned to this cabin OR specifically to the doctor
    $sql = "SELECT id, queue_number, patient_name FROM queues 
            WHERE (clinic_id = ? OR (clinic_id IS NULL AND doctor_id = (SELECT d2.id FROM doctors d2 WHERE d2.current_clinic_id = ? LIMIT 1))) 
            AND status = 'waiting' 
            ORDER BY id ASC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $clinic_id, $clinic_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();

    if ($row) {
        $next_q_id = $row['id'];
        
        // Update patient to serving AND update their clinic_id to the doctors current cabin
        $conn->query("UPDATE queues SET status = 'serving', clinic_id = $clinic_id WHERE id = $next_q_id");
        $conn->query("UPDATE clinic_status SET status = 'serving', current_queue_id = $next_q_id WHERE clinic_id = $clinic_id");
        echo json_encode(['success' => true, 'message' => 'Next patient called']);
    } else {
        $conn->query("UPDATE clinic_status SET status = 'vacant', current_queue_id = NULL WHERE clinic_id = $clinic_id");
        echo json_encode(['success' => true, 'message' => 'No more waiting patients. Clinic marked as vacant.']);
    }
    exit;
}

if ($action == 'mark_done') {
    $data = json_decode(file_get_contents('php://input'), true);
    $clinic_id = $data['clinic_id'] ?? 0;
    $conn->query("UPDATE queues SET status = 'done' WHERE clinic_id = $clinic_id AND status = 'serving'");
    $conn->query("UPDATE clinic_status SET status = 'vacant', current_queue_id = NULL WHERE clinic_id = $clinic_id");
    echo json_encode(['success' => true, 'message' => 'Patient marked as done. Clinic marked as vacant.']);
    exit;
}

if ($action == 'mark_vacant') {
    $data = json_decode(file_get_contents('php://input'), true);
    $clinic_id = $data['clinic_id'] ?? 0;
    $conn->query("UPDATE clinic_status SET status = 'vacant', current_queue_id = NULL WHERE clinic_id = $clinic_id");
    echo json_encode(['success' => true, 'message' => 'Clinic marked as vacant.']);
    exit;
}

if ($action == 'update_ips') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'error' => 'Invalid data format']);
        exit;
    }

    $successCount = 0;
    foreach ($data as $clinic) {
        $id = intval($clinic['id']);
        $ip = trim($clinic['ip']);
        
        if (empty($ip)) {
            $stmt = $conn->prepare("UPDATE clinics SET ip_address = NULL WHERE id = ?");
            $stmt->bind_param("i", $id);
        } else {
            // Basic validation
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                echo json_encode(['success' => false, 'error' => "Invalid IP format: $ip"]);
                exit;
            }
            $stmt = $conn->prepare("UPDATE clinics SET ip_address = ? WHERE id = ?");
            $stmt->bind_param("si", $ip, $id);
        }
        
        if ($stmt->execute()) {
            $successCount++;
        }
    }

    if ($successCount === count($data)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => "Only updated $successCount out of " . count($data) . " clinics."]);
    }
    exit;
}

if ($action == 'create_clinic') {
    $data = json_decode(file_get_contents('php://input'), true);
    $newName = trim($data['clinic_name'] ?? '');

    if (empty($newName)) {
        echo json_encode(['success' => false, 'error' => 'Clinic name cannot be empty']);
        exit;
    }

    // Insert into clinics table
    $stmt = $conn->prepare("INSERT INTO clinics (clinic_number) VALUES (?)");
    $stmt->bind_param("s", $newName);
    
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        // Insert starting status into clinic_status table
        $conn->query("INSERT INTO clinic_status (clinic_id, status) VALUES ($newId, 'vacant')");
        
        echo json_encode(['success' => true, 'id' => $newId, 'name' => $newName]);
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

if ($action == 'create_doctor') {
    $data = json_decode(file_get_contents('php://input'), true);
    $fName = trim($data['firstName'] ?? '');
    $lName = trim($data['lastName'] ?? '');
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? 'doctor';

    if (empty($fName) || empty($lName) || empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        exit;
    }

    $fullName = ($role === 'doctor') ? "Dr. $fName $lName" : "$fName $lName";
    
    // Check if username is already taken
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Username is already taken.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        $stmt_doc = $conn->prepare("INSERT INTO doctors (name) VALUES (?)");
        $stmt_doc->bind_param("s", $fullName);
        $stmt_doc->execute();
        $new_doc_id = $stmt_doc->insert_id;

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt_user = $conn->prepare("INSERT INTO users (username, password, role, relation_id) VALUES (?, ?, ?, ?)");
        $stmt_user->bind_param("sssi", $username, $hash, $role, $new_doc_id);
        $stmt_user->execute();

        $conn->commit();
        echo json_encode(['success' => true, 'id' => $new_doc_id, 'name' => $fullName]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage()]);
    }
    exit;
}

if ($action == 'get_doctor_clinic') {
    $doctor_id = intval($_GET['doctor_id'] ?? 0);
    if ($doctor_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid doctor ID']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT d.current_clinic_id, c.clinic_number 
                            FROM doctors d 
                            LEFT JOIN clinics c ON d.current_clinic_id = c.id 
                            WHERE d.id = ?");
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    
    if ($row && $row['current_clinic_id']) {
        echo json_encode([
            'success' => true, 
            'clinic_id' => $row['current_clinic_id'],
            'clinic_name' => $row['clinic_number']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No active login for this doctor']);
    }
    exit;
}

if ($action == 'update_doctor') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0); // doctor_id
    $userId = intval($data['userId'] ?? 0);
    $fName = trim($data['firstName'] ?? '');
    $lName = trim($data['lastName'] ?? '');
    $newUsername = trim($data['username'] ?? '');
    $role = $data['role'] ?? null;
    $newPassword = $data['password'] ?? '';
    $oldPassword = $data['old_password'] ?? '';
    $skipCheck = isset($data['skip_password_check']);

    if (empty($fName) || empty($lName) || empty($newUsername) || ($id <= 0 && $userId <= 0)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters. First Name, Last Name and Username are required.']);
        exit;
    }
    
    // Fetch user to know current role and current relation_id
    $u_stmt = $conn->prepare("SELECT role, relation_id FROM users WHERE id = ?");
    $u_stmt->bind_param("i", $userId);
    $u_stmt->execute();
    $u_data = $u_stmt->get_result()->fetch_assoc();
    if (!$u_data) {
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        exit;
    }
    $currentRole = $u_data['role'];
    if ($role === null) $role = $currentRole;
    if ($id <= 0) $id = intval($u_data['relation_id']);

    $fullName = ($role === 'doctor') ? "Dr. $fName $lName" : "$fName $lName";

    // VERIFY OLD PASSWORD IF CHANGING PASSWORD OR IF NOT SKIPPING
    if (!$skipCheck || !empty($newPassword)) {
        if (empty($oldPassword)) {
            echo json_encode(['success' => false, 'error' => 'Current password is required to save changes.']);
            exit;
        }
        $auth_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $auth_stmt->bind_param("i", $userId);
        $auth_stmt->execute();
        $auth_res = $auth_stmt->get_result();
        if ($user_data = $auth_res->fetch_assoc()) {
            if (!password_verify($oldPassword, $user_data['password'])) {
                echo json_encode(['success' => false, 'error' => 'Incorrect current password.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'User profile not found.']);
            exit;
        }
    }
    
    // Check if new username is taken by another user
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $check_stmt->bind_param("si", $newUsername, $userId);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Username is already taken by another account.']);
        exit;
    }

    $conn->begin_transaction();
    try {
        if ($id > 0) {
            $stmt_doc = $conn->prepare("UPDATE doctors SET name = ? WHERE id = ?");
            $stmt_doc->bind_param("si", $fullName, $id);
            $stmt_doc->execute();
        }

        if (!empty($newPassword)) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt_user = $conn->prepare("UPDATE users SET username = ?, password = ?, role = ? WHERE id = ?");
            $stmt_user->bind_param("sssi", $newUsername, $hash, $role, $userId);
        } else {
            $stmt_user = $conn->prepare("UPDATE users SET username = ?, role = ? WHERE id = ?");
            $stmt_user->bind_param("ssi", $newUsername, $role, $userId);
        }
        $stmt_user->execute();

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage()]);
    }
    exit;
}

if ($action == 'archive_clinic') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid clinic ID.']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE clinics SET is_archived = 1 WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

if ($action == 'archive_doctor') {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = intval($data['id'] ?? 0);

    if ($userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid Account ID.']);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        // Fetch relation_id to archive the doctor record too if it exists
        $stmt_rel = $conn->prepare("SELECT relation_id FROM users WHERE id = ?");
        $stmt_rel->bind_param("i", $userId);
        $stmt_rel->execute();
        $rel_data = $stmt_rel->get_result()->fetch_assoc();
        $rel_id = $rel_data ? intval($rel_data['relation_id']) : 0;

        if ($rel_id > 0) {
            $stmt = $conn->prepare("UPDATE doctors SET is_archived = 1 WHERE id = ?");
            $stmt->bind_param("i", $rel_id);
            $stmt->execute();
        }

        $stmt_user = $conn->prepare("UPDATE users SET is_archived = 1 WHERE id = ?");
        $stmt_user->bind_param("i", $userId);
        $stmt_user->execute();

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Transaction failed: ' . $e->getMessage()]);
    }
    exit;
}

if ($action == 'unarchive_clinic') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid clinic ID.']);
        exit;
    }
    
    $stmt = $conn->prepare("UPDATE clinics SET is_archived = 0 WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

if ($action == 'unarchive_account') {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = intval($data['id'] ?? 0);

    if ($userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid Account ID.']);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        // Fetch relation_id to unarchive the doctor record too if it exists
        $stmt_rel = $conn->prepare("SELECT relation_id FROM users WHERE id = ?");
        $stmt_rel->bind_param("i", $userId);
        $stmt_rel->execute();
        $rel_data = $stmt_rel->get_result()->fetch_assoc();
        $rel_id = $rel_data ? intval($rel_data['relation_id']) : 0;

        if ($rel_id > 0) {
            $stmt = $conn->prepare("UPDATE doctors SET is_archived = 0 WHERE id = ?");
            $stmt->bind_param("i", $rel_id);
            $stmt->execute();
        }

        $stmt_user = $conn->prepare("UPDATE users SET is_archived = 0 WHERE id = ?");
        $stmt_user->bind_param("i", $userId);
        $stmt_user->execute();

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Unarchive failed: ' . $e->getMessage()]);
    }
    exit;
}

if ($action == 'delete_clinic') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid clinic ID.']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM clinics WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $stmt->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
    exit;
}

if ($action == 'delete_account') {
    $data = json_decode(file_get_contents('php://input'), true);
    $userId = intval($data['id'] ?? 0);

    if ($userId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid Account ID.']);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        // Fetch relation_id to delete the doctor record too if it exists
        $stmt_rel = $conn->prepare("SELECT relation_id FROM users WHERE id = ?");
        $stmt_rel->bind_param("i", $userId);
        $stmt_rel->execute();
        $rel_data = $stmt_rel->get_result()->fetch_assoc();
        $rel_id = $rel_data ? intval($rel_data['relation_id']) : 0;

        if ($rel_id > 0) {
            $stmt = $conn->prepare("DELETE FROM doctors WHERE id = ?");
            $stmt->bind_param("i", $rel_id);
            $stmt->execute();
        }

        $stmt_user = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt_user->bind_param("i", $userId);
        $stmt_user->execute();

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Delete failed: ' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid action']);
