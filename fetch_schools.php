<?php
require_once __DIR__ . '/db.php';
$sql = "SELECT * FROM schools ORDER BY category ASC, school_name ASC";
$result = $conn->query($sql);

$i = 1;

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {

        // Prepare website field
        $website = !empty($row['school_website']) 
            ? "<a href='{$row['school_website']}' target='_blank'>{$row['school_website']}</a>"
            : "<span class='text-muted'>N/A</span>";

        echo "
        <tr>
            <td>{$i}</td>
            <td>{$row['school_name']}</td>

            <!-- ⭐ WEBSITE COLUMN -->
            <td>{$website}</td>

            <td>{$row['category']}</td>
            <td>{$row['status']}</td>

            <td>
                <button class='btn btn-sm btn-edit editSchool' data-id='{$row['id']}'>
                    <i class='bi bi-pencil-fill'></i>
                </button>
                <button class='btn btn-sm btn-delete deleteSchool' data-id='{$row['id']}'>
                    <i class='bi bi-trash-fill'></i>
                </button>
            </td>
        </tr>";

        $i++;
    }
} else {
    echo "<tr><td colspan='6' class='text-center text-muted'>No schools found...</td></tr>";
}
?>
