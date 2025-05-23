<?php
session_start();
require_once '../config/database.php';
// require '../vendor/autoload.php'; // For PHPMailer
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Function to generate random password
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Check election status first for all operations
$stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
$electionStatus = $stmt->fetchColumn();

if ($electionStatus !== 'Pre-Voting') {
    header('Location: manage_voters.php?error=' . urlencode('Voter management is only allowed during the pre-voting phase.'));
    exit();
}

// Handle bulk delete request first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    if (!isset($_POST['voter_ids'])) {
        header('Location: manage_voters.php?error=No voters selected for deletion');
        exit();
    }

    try {
        $voterIds = json_decode($_POST['voter_ids']);
        
        if (empty($voterIds)) {
            header('Location: manage_voters.php?error=No valid voter IDs provided');
            exit();
        }

        // Start transaction
        $pdo->beginTransaction();

        // 1. Delete from otp_codes table
        $placeholders = str_repeat('?,', count($voterIds) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM otp_codes WHERE user_id IN ($placeholders)");
        $stmt->execute($voterIds);
        
        // 2. Delete from votes table
        $stmt = $pdo->prepare("DELETE FROM votes WHERE student_id IN ($placeholders)");
        $stmt->execute($voterIds);
        
        // 3. Get the users' names before deletion
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id IN ($placeholders)");
        $stmt->execute($voterIds);
        $userNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 4. Delete from candidates table if they are candidates
        if (!empty($userNames)) {
            $namePlaceholders = str_repeat('?,', count($userNames) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM candidates WHERE name IN ($namePlaceholders)");
            $stmt->execute($userNames);
        }
        
        // 5. Finally, delete the users
        $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role = 'Student'");
        $stmt->execute($voterIds);

        // Commit the transaction
        $pdo->commit();
        header('Location: manage_voters.php?success=' . urlencode("<div class='alert alert-success mb-3'><i class='bx bx-check-circle me-2'></i>Voter(s) successfully deleted from the system.</div>"));
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in bulk voter deletion process: " . $e->getMessage());
        header('Location: manage_voters.php?error=' . urlencode('System Error: Failed to delete voters.'));
        exit();
    }
}

// Handle single voter delete
if (($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') ||
    ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete')) {
    
    $id = isset($_GET['id']) ? $_GET['id'] : (isset($_POST['voter_id']) ? $_POST['voter_id'] : null);
    
    if (!$id) {
        header('Location: manage_voters.php?error=No voter ID provided');
        exit();
    }

    if (!in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
        header('Location: manage_voters.php?error=Unauthorized action: Only Admins can delete voters');
        exit();
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // First, check if the user exists and is a student
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'Student'");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new PDOException("Voter not found or is not a student");
        }

        // Check the election status
        $stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
        $electionStatus = $stmt->fetchColumn();

        if ($electionStatus === 'Voting') {
            $pdo->rollBack();
            header('Location: manage_voters.php?error=' . urlencode('Cannot delete voters during the voting phase. Please wait until the pre-voting phase to manage voters.'));
            exit();
        } else if ($electionStatus === 'Ended') {
            $pdo->rollBack();
            header('Location: manage_voters.php?error=' . urlencode('Cannot delete voters after the election has ended. Please wait until the next pre-voting phase.'));
            exit();
        }

        if ($electionStatus === 'Pre-Voting') {
            try {
                // Make sure no transaction is active before starting a new one
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                // Start transaction
                $pdo->beginTransaction();

                // 1. First, delete from otp_codes table
                $stmt = $pdo->prepare("DELETE FROM otp_codes WHERE user_id = ?");
                $stmt->execute([$id]);
                
                // 2. Delete from votes table
                $stmt = $pdo->prepare("DELETE FROM votes WHERE student_id = ?");
                $stmt->execute([$id]);
                
                // 3. Get the user's name before deletion
                $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $userName = $stmt->fetchColumn();
                
                // 4. Delete from candidates table if they are a candidate
                if ($userName) {
                    $stmt = $pdo->prepare("DELETE FROM candidates WHERE name = ?");
                    $stmt->execute([$userName]);
                }
                
                // 5. Finally, delete the user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'Student'");
                if (!$stmt->execute([$id])) {
                    throw new PDOException("Failed to delete user record");
                }

                // If we got here, everything worked
                $pdo->commit();
                header('Location: manage_voters.php?success=' . urlencode("<div class='alert alert-success mb-3'><i class='bx bx-check-circle me-2'></i>Voter successfully deleted from the system.</div>"));
                exit();
            } catch (PDOException $e) {
                // Make sure to rollback if there's an active transaction
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                
                error_log("Detailed error while deleting voter: " . $e->getMessage());
                
                // More specific error handling
                if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                    header('Location: manage_voters.php?error=' . urlencode('Cannot delete voter: They have related records in the system.'));
                } else {
                    header('Location: manage_voters.php?error=' . urlencode('Failed to delete voter. Please try again.'));
                }
                exit();
            }
        } else {
            // For non-pre-voting phases
            $stmt = $pdo->prepare("SELECT 
                (SELECT COUNT(*) FROM votes WHERE student_id = ?) as has_voted,
                (SELECT COUNT(*) FROM candidates WHERE name = (SELECT name FROM users WHERE id = ?)) as is_candidate");
            $stmt->execute([$id, $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result['has_voted'] && $result['is_candidate']) {
                header('Location: manage_voters.php?error=' . urlencode('Cannot delete: Voter is a candidate and has already cast their vote. Please wait for pre-voting phase.'));
            } else if ($result['has_voted']) {
                header('Location: manage_voters.php?error=' . urlencode('Cannot delete: Voter has already cast their vote. Please wait for pre-voting phase.'));
            } else if ($result['is_candidate']) {
                header('Location: manage_voters.php?error=' . urlencode('Cannot delete: Voter is registered as a candidate. Please wait for pre-voting phase.'));
            }
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            exit();
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error in voter deletion process: " . $e->getMessage());
        header('Location: manage_voters.php?error=' . urlencode('System Error: ' . $e->getMessage()));
    }
    exit();
}

// Handle regular form submissions (Add/Edit voter)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $lrn = trim($_POST['lrn']);

        // Validate name (letters and spaces only)
        if (!preg_match('/^[A-Za-z\s]+$/', $name)) {
            header('Location: manage_voters.php?error=Full name should only contain letters and spaces');
            exit();
        }

        // Check if LRN already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE lrn = ?");
        $stmt->execute([$lrn]);
        if ($stmt->fetchColumn() > 0) {
            header('Location: manage_voters.php?error=LRN already exists');
            exit();
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            header('Location: manage_voters.php?error=Email already exists');
            exit();
        }

        // Generate a random password
        $password = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, lrn, role) VALUES (?, ?, ?, ?, 'Student')");
            $stmt->execute([$name, $email, $hashed_password, $lrn]);
            
            // Send email with credentials using PHPMailer
            try {
                $mail = new PHPMailer(true);
                
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'sulivannationalhighschool@gmail.com';
                $mail->Password = 'admf ihhi fruj jlcu';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                // Recipients
                $mail->setFrom('sulivannationalhighschool@gmail.com', 'E-VOTE');
                $mail->addAddress($email, $name);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Your E-VOTE! Account Credentials';
                $mail->Body = "
                    <h2>Welcome to E-VOTE!</h2>
                    <p>Hello {$name},</p>
                    <p>Your account has been created successfully.</p>
                    <p>Here are your login credentials:</p>
                    <p>Email: {$email}</p>
                    <p>Temporary Password:</p>
                    <div style='background-color: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                        <h1 style='color: #393CB2; margin: 0; letter-spacing: 5px;'>{$password}</h1>
                    </div>
                    <p>Please log in and change your password immediately.</p>
                    <p>Best regards,<br>E-VOTE! Team</p>
                ";

                $mail->send();
                header('Location: manage_voters.php?success=' . urlencode("<div class='alert alert-success alert-dismissible fade show' role='alert'><i class='bx bx-check-circle me-2'></i>Voter added successfully. Login credentials have been sent via email.<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>"));
            } catch (Exception $e) {
                error_log("Failed to send email: " . $mail->ErrorInfo);
                header('Location: manage_voters.php?error=Voter added but failed to send email notification');
            }
            exit();
        } catch (PDOException $e) {
            header('Location: manage_voters.php?error=Failed to add voter');
            exit();
        }
    } else if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        // Edit existing voter
        $voter_id = $_POST['voter_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $lrn = trim($_POST['lrn']);

        // Validate name (letters and spaces only)
        if (!preg_match('/^[A-Za-z\s]+$/', $name)) {
            header('Location: manage_voters.php?error=Full name should only contain letters and spaces');
            exit();
        }

        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, lrn = ? WHERE id = ? AND role = 'Student'");
        $stmt->execute([$name, $email, $lrn, $voter_id]);
        header('Location: manage_voters.php?success=Voter updated successfully');
    }
    exit();
}

// If we get here, redirect back to the manage voters page
header('Location: manage_voters.php');
exit();
?>
