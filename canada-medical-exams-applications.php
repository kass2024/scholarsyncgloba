<?php
/**
 * canada-medical-exams-applications.php
 * View Canada Medical Exams Applications
 */

session_start();
require_once __DIR__ . '/db.php';

// Check if admin is logged in
$admin_id = $_SESSION['id'] ?? null;
if (!$admin_id || !isset($_SESSION['role'])) {
    header("Location: admin-login.php");
    exit;
}

// Check if user has permission
$role = $_SESSION['role'];
$allowed_roles = ['superadmin', 'staff'];
if (!in_array($role, $allowed_roles)) {
    header("Location: admin-dashboard.php");
    exit;
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_filter = $_GET['date'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build WHERE clause
$where_conditions = ["1=1"];
$params = [];
$types = '';

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($date_filter !== 'all') {
    switch ($date_filter) {
        case 'today':
            $where_conditions[] = "DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            break;
    }
}

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR reference_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    $types .= 'ssss';
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Count total records
$count_sql = "SELECT COUNT(*) as total FROM canada_medical_exams_requests $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get applications
$sql = "SELECT * FROM canada_medical_exams_requests $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $all_params = array_merge($params, [$per_page, $offset]);
    $all_types = $types . 'ii';
    $stmt->bind_param($all_types, ...$all_params);
} else {
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canada Medical Exams Applications | ScholarSync Global</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-700: #374151;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #f8fafc;
            color: var(--gray-700);
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
            text-align: center;
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .stats-label {
            color: var(--gray-700);
            font-weight: 500;
            margin-top: 0.5rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-under_review {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-approved {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
        }
        
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--gray-200);
        }
        
        @media (max-width: 768px) {
            .stats-card {
                margin-bottom: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .table-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h1 class="mb-0">
                        <i class="fas fa-hospital me-3"></i>
                        Canada Medical Exams Applications
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Manage and review medical examination requests</p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <a href="admin-dashboard.php" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $total_records; ?></div>
                    <div class="stats-label">Total Applications</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-number text-warning">
                        <?php 
                        $pending_count = 0;
                        foreach ($applications as $app) {
                            if ($app['status'] === 'pending') $pending_count++;
                        }
                        echo $pending_count; 
                        ?>
                    </div>
                    <div class="stats-label">Pending Review</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-number text-info">
                        <?php 
                        $review_count = 0;
                        foreach ($applications as $app) {
                            if ($app['status'] === 'under_review') $review_count++;
                        }
                        echo $review_count; 
                        ?>
                    </div>
                    <div class="stats-label">Under Review</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stats-card">
                    <div class="stats-number text-success">
                        <?php 
                        $approved_count = 0;
                        foreach ($applications as $app) {
                            if ($app['status'] === 'approved') $approved_count++;
                        }
                        echo $approved_count; 
                        ?>
                    </div>
                    <div class="stats-label">Approved</div>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="date" class="form-label">Date Range</label>
                    <select class="form-select" id="date" name="date">
                        <option value="all" <?php echo $date_filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="week" <?php echo $date_filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                        <option value="month" <?php echo $date_filter === 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Name, email, or reference ID..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label><br>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Applications Table -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="mb-0">Applications List</h3>
                <div class="text-muted">
                    Showing <?php echo min($per_page, $total_records - $offset); ?> of <?php echo $total_records; ?> applications
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover" id="applicationsTable">
                    <thead>
                        <tr>
                            <th>Reference ID</th>
                            <th>Applicant</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-0">No applications found matching your criteria.</p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <code><?php echo htmlspecialchars($app['reference_id']); ?></code>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['address']); ?></small>
                                </td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($app['email']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($app['email']); ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="tel:+<?php echo htmlspecialchars($app['phone_area_code'] . $app['phone_number']); ?>" class="text-decoration-none">
                                        +<?php echo htmlspecialchars($app['phone_area_code'] . ' ' . $app['phone_number']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $app['status']; ?>">
                                        <?php echo ucwords(str_replace('_', ' ', $app['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo date('M j, Y', strtotime($app['created_at'])); ?></div>
                                    <small class="text-muted"><?php echo date('g:i A', strtotime($app['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewDetails(<?php echo $app['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-sm btn-outline-success" onclick="updateStatus(<?php echo $app['id']; ?>, 'approved')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="updateStatus(<?php echo $app['id']; ?>, 'under_review')">
                                            <i class="fas fa-clock"></i> Review
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" onclick="updateStatus(<?php echo $app['id']; ?>, 'rejected')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Application Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Application Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Content loaded via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    function viewDetails(applicationId) {
        const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
        const modalBody = document.getElementById('modalBody');
        
        modalBody.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading application details...</p>
            </div>
        `;
        
        modal.show();
        
        fetch('canada_medical_application_details.php?id=' + applicationId)
            .then(response => response.text())
            .then(html => {
                modalBody.innerHTML = html;
            })
            .catch(error => {
                modalBody.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Failed to load application details.
                    </div>
                `;
            });
    }
    
    function updateStatus(applicationId, newStatus) {
        if (!confirm(`Are you sure you want to change the status to "${newStatus.replace('_', ' ')}"?`)) {
            return;
        }
        
        const formData = new FormData();
        formData.append('application_id', applicationId);
        formData.append('status', newStatus);
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token'] ?? ''; ?>');
        
        fetch('update_canada_medical_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Status updated successfully!');
                location.reload();
            } else {
                alert('Failed to update status: ' + data.message);
            }
        })
        .catch(error => {
            alert('Error updating status: ' + error.message);
        });
    }
    
    // Initialize DataTable
    $(document).ready(function() {
        $('#applicationsTable').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[5, 'desc']] // Sort by submission date
        });
    });
    </script>
</body>
</html>
