<?php
require_once __DIR__ . '/db.php';
// Fetch all members ordered by newest first
$result = $conn->query("SELECT * FROM members ORDER BY id DESC");

if ($result && $result->num_rows > 0) {
    $i = 1;
    while ($row = $result->fetch_assoc()) {
        $statusClass = ($row['status'] === 'Active') ? 'badge-active' : 'badge-inactive';

        // Format appointment date
        $appt = !empty($row['appointment_date'])
            ? "<span class='badge-date'><i class='bi bi-calendar-event me-1'></i>" . date('M d, Y', strtotime($row['appointment_date'])) . "</span>"
            : "<span class='text-muted'>N/A</span>";

        echo "
        <tr>
            <td>{$i}</td>
            <td class='fw-semibold text-capitalize'>{$row['fullname']}</td>
            <td>{$row['email']}</td>
            <td>{$row['phone']}</td>
            <td>{$row['country']}</td>
            <td>{$row['membership']}</td>
            <td>
                <span class='badge {$statusClass} toggleStatus' data-id='{$row['id']}' style='cursor:pointer;'>
                    {$row['status']}
                </span>
            </td>
            <td>{$appt}</td>
            <td class='text-center'>
                <button class='btn btn-sm btn-edit editMember' data-id='{$row['id']}'>
                    <i class='bi bi-pencil-square'></i> Edit
                </button>
                <button class='btn btn-sm btn-delete deleteMember' data-id='{$row['id']}'>
                    <i class='bi bi-trash'></i> Delete
                </button>
            </td>
        </tr>";
        $i++;
    }
} else {
    echo "
    <tr>
        <td colspan='9' class='text-center text-muted py-3'>
            <i class='bi bi-info-circle me-2'></i> No members found.
        </td>
    </tr>";
}
?>
