<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin with proper role names
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Get current election status
$stmt = $pdo->query("SELECT status FROM election_status ORDER BY id DESC LIMIT 1");
$electionStatus = $stmt->fetchColumn();

// Get all voters (students) ordered by ID for first-come-first-serve
$stmt = $pdo->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM votes v WHERE v.student_id = u.id) as has_voted
    FROM users u 
    WHERE u.role = 'Student'
    ORDER BY u.id
");
$voters = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Voters - E-VOTE!</title>
    <link rel="icon" type="image/x-icon" href="/Sulivan-E-Voting-System/image/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="css/admin-shared.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #393CB2;
            --primary-light: #5558CD;
            --primary-dark: #2A2D8F;
            --accent-color: #E8E9FF;
            --gradient-primary: linear-gradient(135deg, #393CB2, #5558CD);
            --light-bg: #F8F9FF;
        }

        body {
            background: var(--light-bg);
            min-height: 100vh;
        }

        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            background: var(--primary-color);
            color: white;
            box-shadow: 4px 0 10px rgba(57, 60, 178, 0.1);
            z-index: 1000;
        }

        .main-content {
            margin-left: 260px;
            padding: 2rem;
            background: var(--light-bg);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            background: var(--primary-color);
        }

        .sidebar-brand img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 100px;
            margin-right: 12px;
        }

        .sidebar-brand h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1.5rem;
            margin: 0.25rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            white-space: nowrap;
            text-decoration: none;
            position: relative;
            overflow: hidden;
        }

        .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .nav-link.active {
            background: white;
            color: var(--primary-color);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            font-weight: 600;
        }

        .nav-link.active i {
            color: var(--primary-color);
        }

        .nav-link i {
            margin-right: 12px;
            font-size: 1.25rem;
            width: 24px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .nav-link span {
            font-size: 0.95rem;
        }

        .nav-link:not(.active):hover i {
            transform: scale(1.1);
        }

        /* Enhanced Table Styles */
        .table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(57, 60, 178, 0.05);
            margin-bottom: 0;
        }

        .table th {
            background: var(--light-bg);
            color: #666;
            font-weight: 500;
            border-bottom: 2px solid #eee;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            color: #444;
            border-bottom: 1px solid #eee;
        }

        .table tbody tr:hover {
            background-color: var(--light-bg);
        }

        /* Card Styles */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(57, 60, 178, 0.05);
            background: white;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            padding: 1rem 1.25rem;
        }

        .card-title {
            color: var(--primary-color);
            font-weight: 600;
            margin: 0;
        }

        /* Button Styles */
        .btn-modal-cancel {
            background: var(--accent-color);
            color: var(--primary-color);
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-modal-cancel:hover {
            background: #d8daff;
            transform: translateY(-1px);
        }

        /* Status Badge */
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-badge.voted {
            background: #E8FFF1;
            color: #0D9448;
        }

        .status-badge.not-voted {
            background: #FFF5E8;
            color: #B65C12;
        }

        /* Search and Length Control */
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 0.375rem 0.75rem;
        }

        .dataTables_wrapper .dataTables_length select:focus,
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(85, 88, 205, 0.1);
            outline: none;
        }

        /* DataTables Custom Styling */
        .dataTables_length {
            margin-bottom: 1rem;
        }
        
        .dataTables_length select {
            padding: 0.375rem 2.25rem 0.375rem 0.75rem;
            font-size: 0.9rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            min-width: 65px;
        }

        .dataTables_length select:focus {
            border-color: var(--primary-light);
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(85, 88, 205, 0.25);
        }

        .dataTables_length label {
            font-weight: 500;
            color: #666;
        }

        .dataTables_filter input {
            padding: 0.375rem 0.75rem;
            font-size: 0.9rem;
            font-weight: 400;
            line-height: 1.5;
            color: #212529;
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
        }

        .dataTables_filter input:focus {
            border-color: var(--primary-light);
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(85, 88, 205, 0.25);
        }

        /* Fix dropdown alignment */
        div.dataTables_length select {
            width: 50px !important;
        }

        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(57, 60, 178, 0.1);
        }

        .modal-header {
            background: var(--light-bg);
            border-bottom: 1px solid #eee;
            border-radius: 12px 12px 0 0;
            padding: 1.25rem;
        }

        .modal-title {
            color: var(--primary-color);
            font-weight: 600;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-label {
            color: #555;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control {
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(85, 88, 205, 0.1);
        }

        /* Action Buttons */
        .action-btn {
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .action-btn i {
            font-size: 1.1rem;
            margin-right: 0.25rem;
        }

        /* Alert Styles */
        .alert {
            border-radius: 10px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border: none;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }

        .alert i {
            font-size: 1.25rem;
            vertical-align: middle;
        }

        /* Floating Action Button */
        .fab-container {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 999;
        }

        .fab {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--gradient-primary);
            box-shadow: 0 4px 12px rgba(57, 60, 178, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .fab:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(57, 60, 178, 0.25);
        }

        .fab i {
            font-size: 1.75rem;
        }

        .fab::after {
            content: 'Add New Voter';
            position: absolute;
            right: calc(100% + 10px);
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .fab:hover::after {
            opacity: 1;
            visibility: visible;
            right: calc(100% + 15px);
        }

        @media (max-width: 768px) {
            .fab-container {
                bottom: 1.5rem;
                right: 1.5rem;
            }
            
            .fab::after {
                display: none;
            }
        }

        /* DataTables info text styling */
        .dataTables_info {
            color: #6c757d;  /* Gray text color */
            padding-top: 0.5rem;
        }

        /* DataTables length label styling */
        .dataTables_length label {
            font-weight: 500;
            color: #666;
        }

        /* DataTables Pagination Styling */
        .dataTables_paginate .paginate_button {
            border: 1px solidrgb(0, 128, 255);
            background: white;
            border-radius: 6px;
            color: var(--primary-color) !important;
            margin-top: 5px;
            margin-left: 5px;
        }

        .dataTables_paginate .paginate_button:hover {
            background: var(--accent-color) !important;
            border-color: var(--primary-light);
            color: var(--primary-color) !important;
        }

        .dataTables_paginate .paginate_button.current {
            background: var(--primary-color) !important;
            border-color: var(--primary-color);
            color: white !important;
        }

        .dataTables_paginate .paginate_button.disabled {
            color: #6c757d !important;
            border-color: #dee2e6;
            background: #f8f9fa !important;
        }

        .dataTables_paginate .paginate_button.disabled:hover {
            background: #f8f9fa !important;
            border-color: #dee2e6;
        }

        /* Checkbox styling */
        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin-top: 0;
            vertical-align: middle;
            background-color: #fff;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            border: 1px solid #ced4da;
            appearance: none;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10l3 3l6 -6'/%3e%3c/svg%3e");
        }

        .form-check-input:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 0.25rem rgba(85, 88, 205, 0.25);
        }

        /* Bulk delete button styling */
        .btn-bulk-delete {
            display: none;
            margin-right: 0.5rem;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-bulk-delete:hover {
            
            transform: translateY(-1px);
        }

        .btn-bulk-delete i {
            margin-right: 0.5rem;
        }

        /* Table header checkbox styling */
        .table th:first-child {
            width: 40px;
            text-align: center;
        }

        .table td:first-child {
            text-align: center;
        }

        /* Add these styles to your existing styles */
        .import-results .alert {
            border: none;
            border-radius: 8px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .import-results .alert-success {
            background-color: #E8FFF1;
            color: #0D9448;
            border-left: 4px solid #0D9448;
        }

        .import-results .alert-warning {
            background-color: #FFF5E8;
            color: #B65C12;
            border-left: 4px solid #B65C12;
        }

        .import-results .alert-info {
            background-color: #E8F4FF;
            color: #0D6EFD;
            border-left: 4px solid #0D6EFD;
        }

        .import-results ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        .import-results li {
            margin-bottom: 0.25rem;
        }

        .import-results li:last-child {
            margin-bottom: 0;
        }

        .import-results h6 {
            color: inherit;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <div class="sidebar-brand">
                    <img src="../image/Untitled.jpg" alt="E-VOTE! Logo">
                    <h3>E-VOTE!</h3>
                </div>
                <div class="nav flex-column">
                    <a href="dashboard.php" class="nav-link">
                        <i class='bx bxs-dashboard'></i>
                        <span>Dashboard</span>
                    </a>
                    <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                    <a href="manage_candidates.php" class="nav-link">
                        <i class='bx bxs-user-detail'></i>
                        <span>Manage Candidates</span>
                    </a>
                    <a href="manage_positions.php" class="nav-link">
                        <i class='bx bxs-badge'></i>
                        <span>Manage Positions</span>
                    </a>
                    <?php endif; ?>
                    <a href="manage_voters.php" class="nav-link active">
                        <i class='bx bxs-group'></i>
                        <span>Manage Voters</span>
                    </a>
                    <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                    <a href="manage_admins.php" class="nav-link">
                        <i class='bx bxs-user-account'></i>
                        <span>Manage Admins</span>
                    </a>
                    <?php endif; ?>
                    <a href="election_results.php" class="nav-link">
                        <i class='bx bxs-bar-chart-alt-2'></i>
                        <span>Election Results</span>
                    </a>
                    <a href="../auth/logout.php" class="nav-link">
                        <i class='bx bxs-log-out'></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="section-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <h2 class="mb-3" style="font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; font-size: 24px; color: var(--primary-color);">Manage Voters</h2>
                        </div>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <?php echo $_GET['success']; ?>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class='bx bx-error-circle me-2'></i><?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($electionStatus !== 'Pre-Voting'): ?>
                    <div class="alert alert-info mb-4">
                        <i class='bx bx-info-circle me-2'></i>
                        <?php if ($electionStatus === 'Voting'): ?>
                            Voter management is disabled during the voting phase. Please wait until the pre-voting phase to add or modify voters.
                        <?php else: ?>
                            Voter management is disabled after the election has ended. Please wait until the next pre-voting phase.
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            Voter List
                            <span class="text-muted ms-2" style="font-size: 0.9rem;">
                                (<?php echo count($voters); ?> total)
                            </span>
                        </h5>
                        <?php if ($electionStatus === 'Pre-Voting'): ?>
                            <div class="d-flex gap-2">
                                <button id="deleteSelected" class="btn btn-danger btn-bulk-delete">
                                    <i class='bx bx-trash me-2'></i>Delete Selected
                                </button>
                                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importVoterModal">
                                    <i class='bx bx-import me-2'></i>Import Voters
                                </button>
                                <button class="btn-add-main" data-bs-toggle="modal" data-bs-target="#addVoterModal">
                                    <i class='bx bx-plus'></i>
                                    Add New Voter
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table" id="votersTable">
                                <thead>
                                    <tr>
                                        <th>
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                    
                                        <th data-bs-toggle="tooltip" title="Voter's full name">Name</th>
                                        <th data-bs-toggle="tooltip" title="Voter's email address for login">Email</th>
                                        <th data-bs-toggle="tooltip" title="Voter's LRN">LRN</th>
                                        <th data-bs-toggle="tooltip" title="Current voting status">Status</th>
                                        <th data-bs-toggle="tooltip" title="Available actions depend on election phase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($voters as $voter): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input voter-checkbox" value="<?php echo $voter['id']; ?>">
                                        </td>
                                        <td><?php echo htmlspecialchars($voter['name']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['email']); ?></td>
                                        <td><?php echo htmlspecialchars($voter['lrn']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $voter['has_voted'] ? 'voted' : 'not-voted'; ?>"
                                                  data-bs-toggle="tooltip" 
                                                  title="<?php echo $voter['has_voted'] ? 'This voter has cast their vote' : 'This voter has not yet voted'; ?>">
                                                <?php echo $voter['has_voted'] ? 'Voted' : 'Not Voted'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($electionStatus === 'Pre-Voting'): ?>
                                            <div class="d-flex gap-2">
                                                <form action="process_voter.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="voter_id" value="<?php echo $voter['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger action-btn" 
                                                            data-bs-toggle="tooltip" 
                                                            title="Remove voter and their voting records">
                                                        <i class='bx bx-trash'></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted" data-bs-toggle="tooltip" title="Voter management is disabled during voting">
                                                <i class='bx bx-lock-alt'></i> Locked
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($electionStatus === 'Pre-Voting'): ?>
    <!-- Floating Action Button -->
    <div class="fab-container">
        <button class="fab" data-bs-toggle="modal" data-bs-target="#addVoterModal" aria-label="Add New Voter">
            <i class='bx bx-plus'></i>
        </button>
    </div>
    <?php endif; ?>

    <!-- Add Voter Modal -->
    <div class="modal fade" id="addVoterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Voter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    <form action="process_voter.php" method="POST" id="addVoterForm">
                        <input type="hidden" name="action" value="add">
                        <div id="addVoterError" class="alert alert-danger d-none"></div>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="lrn" class="form-label">LRN (Learner Reference Number)</label>
                            <input type="text" class="form-control" id="lrn" name="lrn" 
                                   pattern="[0-9]{12}" 
                                   maxlength="12" 
                                   oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                   required>
                            <small class="text-muted">Enter exactly 12 numbers</small>
                        </div>
                       
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-add-main">Add Voter</button>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Edit Voter Modal -->
    <div class="modal fade" id="editVoterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Voter</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <form action="process_voter.php" method="POST" id="editVoterForm">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="voter_id" id="edit_voter_id">
                        <div id="editVoterError" class="alert alert-danger d-none"></div>
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="edit_password" name="password" 
                                       placeholder="Leave blank to keep current password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class='bx bx-show'></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Only fill this if you want to change the password</small>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-add-main"><i class='bx bx-save me-2'></i>Save Changes</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Import Voter Modal -->
    <div class="modal fade" id="importVoterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Voters from Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class='bx bx-info-circle me-2'></i>
                        Please ensure your Excel file has the following columns in order:
                        <ol class="mt-2">
                            <li>Full Name</li>
                            <li>Email Address</li>
                            <li>LRN (Learner Reference Number)</li>
                        </ol>
                    </div>
                    <form action="process_excel.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="import">
                        <div class="mb-3">
                            <label for="excel_file" class="form-label">Select Excel File</label>
                            <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx,.xls" required>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-modal-cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">
                                <i class='bx bx-import me-2'></i>Import
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize all tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover'
                });
            });

            // Initialize DataTable with proper ordering
            $('#votersTable').DataTable({
                pageLength: 10,
                language: {
                    search: "",
                    searchPlaceholder: "Search voters...",
                    info: "Showing _START_ to _END_ of _TOTAL_ voters",
                    infoEmpty: "No voters found",
                    emptyTable: "No voters available",
                    paginate: {
                        first: '<i class="bx bx-chevrons-left"></i>',
                        last: '<i class="bx bx-chevrons-right"></i>',
                        next: '<i class="bx bx-chevron-right"></i>',
                        previous: '<i class="bx bx-chevron-left"></i>'
                    }
                },
                columnDefs: [
                    { orderable: false, targets: 0 }, // Disable sorting on checkbox column
                    { orderable: false, targets: -1 } // Disable sorting on action column
                ],
                order: [[1, 'asc']] // Sort by name column by default
            });

            // Password visibility toggle
            $('#togglePassword').click(function() {
                const passwordInput = $('#edit_password');
                const icon = $(this).find('i');
                
                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('bx-show').addClass('bx-hide');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('bx-hide').addClass('bx-show');
                }
            });

            // Handle Select All checkbox
            $('#selectAll').change(function() {
                $('.voter-checkbox').prop('checked', $(this).is(':checked'));
                updateDeleteButtonVisibility();
            });

            // Handle individual checkboxes
            $(document).on('change', '.voter-checkbox', function() {
                updateDeleteButtonVisibility();
                // Update select all checkbox
                $('#selectAll').prop('checked', $('.voter-checkbox:checked').length === $('.voter-checkbox').length);
            });

            // Function to show/hide delete button
            function updateDeleteButtonVisibility() {
                const checkedCount = $('.voter-checkbox:checked').length;
                $('#deleteSelected').toggle(checkedCount > 0);
            }

            // Handle bulk delete
            $('#deleteSelected').click(function(e) {
                e.preventDefault(); // Prevent any default form submission
                
                const selectedIds = $('.voter-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                if (selectedIds.length === 0) {
                    alert('Please select at least one voter to delete.');
                    return;
                }

                // Directly submit the form without confirmation
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'process_voter.php';
                form.style.display = 'none'; // Hide the form

                // Add bulk_delete action
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'bulk_delete';
                form.appendChild(actionInput);

                // Add voter_ids
                const voterIdsInput = document.createElement('input');
                voterIdsInput.type = 'hidden';
                voterIdsInput.name = 'voter_ids';
                voterIdsInput.value = JSON.stringify(selectedIds);
                form.appendChild(voterIdsInput);

                // Append form to body and submit
                document.body.appendChild(form);
                form.submit();
            });

            // Add Voter Form Validation
            $('#addVoterForm').submit(function(e) {
                $('#addVoterError').addClass('d-none').text('');
                let errorMessage = '';
                const name = $('#name').val().trim();
                if (!name) {
                    errorMessage += 'Please enter full name.<br>';
                } else if (!/^[A-Za-z\s]+$/.test(name)) {
                    errorMessage += 'Full name should only contain letters and spaces.<br>';
                }
                if (errorMessage) {
                    $('#addVoterError').removeClass('d-none').html(errorMessage);
                    e.preventDefault();
                    return false;
                }
            });

            // Edit Voter Form Validation
            $('#editVoterForm').submit(function(e) {
                $('#editVoterError').addClass('d-none').text('');
                let errorMessage = '';
                const name = $('#edit_name').val().trim();
                if (!name) {
                    errorMessage += 'Please enter full name.<br>';
                } else if (!/^[A-Za-z\s]+$/.test(name)) {
                    errorMessage += 'Full name should only contain letters and spaces.<br>';
                }
                if (errorMessage) {
                    $('#editVoterError').removeClass('d-none').html(errorMessage);
                    e.preventDefault();
                    return false;
                }
            });
        });

        function editVoter(id, name, email, lrn) {
            document.getElementById('edit_voter_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_lrn').value = lrn;
            document.getElementById('edit_password').value = '';
            new bootstrap.Modal(document.getElementById('editVoterModal')).show();
        }
    </script>
</body>
</html>
