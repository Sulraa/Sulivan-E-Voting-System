<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Super Admin', 'Sub-Admin'])) {
    header('Location: ../index.php');
    exit();
}

// Get all positions ordered by ID (so newer positions appear at the bottom)
$stmt = $pdo->query("SELECT * FROM positions ORDER BY id ASC");
$positions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Positions - E-VOTE!</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        .sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px;
            background-color: #343a40;
            color: white;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .nav-link {
            color: white;
            margin-bottom: 10px;
        }
        .nav-link:hover {
            color: #17a2b8;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .position-card {
            transition: transform 0.2s;
        }
        .position-card:hover {
            transform: translateY(-5px);
        }
        .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: white;
        }
        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar">
                <h3 class="mb-4">E-VOTE!</h3>
                <div class="nav flex-column">
                    <a href="dashboard.php" class="nav-link">
                        <i class='bx bxs-dashboard'></i> Dashboard
                    </a>
                    <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                    <a href="manage_candidates.php" class="nav-link">
                        <i class='bx bxs-user-detail'></i> Manage Candidates
                    </a>
                    <a href="manage_positions.php" class="nav-link active">
                        <i class='bx bxs-badge'></i> Manage Positions
                    </a>
                    <?php endif; ?>
                    <a href="manage_voters.php" class="nav-link">
                        <i class='bx bxs-user-account'></i> Manage Voters
                    </a>
                    <?php if ($_SESSION['user_role'] === 'Super Admin'): ?>
                    <a href="manage_admins.php" class="nav-link">
                        <i class='bx bxs-user-check'></i> Manage Sub-Admins
                    </a>
                    <?php endif; ?>
                    <a href="election_results.php" class="nav-link">
                        <i class='bx bxs-bar-chart-alt-2'></i> Election Results
                    </a>
                    <a href="../auth/logout.php" class="nav-link text-danger mt-5">
                        <i class='bx bxs-log-out'></i> Logout
                    </a>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Manage Positions</h2>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPositionModal">
                        <i class='bx bx-plus'></i> Add Position
                    </button>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Positions List -->
                <div class="row">
                    <?php foreach ($positions as $position): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card position-card">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($position['position_name']); ?></h5>
                                    <p class="text-muted">Max Votes: <?php echo $position['max_votes']; ?></p>
                                    <div class="mt-3">
                                        <button class="btn btn-sm btn-info edit-position" 
                                                data-id="<?php echo $position['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($position['position_name']); ?>"
                                                data-max-votes="<?php echo $position['max_votes']; ?>">
                                            <i class='bx bx-edit'></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger delete-position"
                                                data-id="<?php echo $position['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($position['position_name']); ?>">
                                            <i class='bx bx-trash'></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Position Modal -->
    <div class="modal fade" id="addPositionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Position</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process_position.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="position_name" class="form-label">Position Name</label>
                            <input type="text" class="form-control" id="position_name" name="position_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="max_votes" class="form-label">Maximum Votes Allowed</label>
                            <input type="number" class="form-control" id="max_votes" name="max_votes" min="1" value="1" required>
                            <small class="text-muted">Set to 2 or more to allow multiple selections</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="add" class="btn btn-primary">Add Position</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Position Modal -->
    <div class="modal fade" id="editPositionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Position</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process_position.php" method="POST">
                    <input type="hidden" name="position_id" id="edit_position_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_position_name" class="form-label">Position Name</label>
                            <input type="text" class="form-control" id="edit_position_name" name="position_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_max_votes" class="form-label">Maximum Votes Allowed</label>
                            <input type="number" class="form-control" id="edit_max_votes" name="max_votes" min="1" required>
                            <small class="text-muted">Set to 2 or more to allow multiple selections</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="edit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Position Modal -->
    <div class="modal fade" id="deletePositionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Position</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="process_position.php" method="POST">
                    <input type="hidden" name="position_id" id="delete_position_id">
                    <div class="modal-body">
                        <p>Are you sure you want to delete the position "<span id="delete_position_name"></span>"?</p>
                        <p class="text-danger">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="delete" class="btn btn-danger">Delete Position</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Edit position
            $('.edit-position').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const maxVotes = $(this).data('max-votes');
                $('#edit_position_id').val(id);
                $('#edit_position_name').val(name);
                $('#edit_max_votes').val(maxVotes);
                $('#editPositionModal').modal('show');
            });

            // Delete position
            $('.delete-position').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                $('#delete_position_id').val(id);
                $('#delete_position_name').text(name);
                $('#deletePositionModal').modal('show');
            });
        });
    </script>
</body>
</html>
