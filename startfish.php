<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>ScholarSync Global Visa | Member Management</title>

  <!-- CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_green.css" rel="stylesheet">

  <!-- JS Libraries -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    body {
      background: linear-gradient(135deg, #f3faf4, #ffffff);
      font-family: 'Poppins', sans-serif;
      font-size: 0.9rem;
    }
    .brand-logo { width: 100px; display: block; margin: 0 auto 10px; }
    .btn-scholar {
      background-color: #004d25; color: white; font-weight: 500;
      border: none; border-radius: 30px; font-size: 0.9rem;
    }
    .btn-scholar:hover { background-color: #007b43; }
    .card { border: none; border-radius: 15px; }
    .card-header {
      background: #006b3c; color: white; font-weight: 600;
      text-align: center; font-size: 1.1rem;
    }
    table.dataTable thead { background-color: #004d25; color: #fff; font-size: 0.85rem; }
    table.dataTable td, table.dataTable th {
      vertical-align: middle; font-size: 0.85rem;
    }
    .dashboard-btn {
      background-color: #004d25; color: white; border-radius: 30px;
      font-weight: 500; padding: 6px 18px; text-decoration: none; font-size: 0.85rem;
    }
    .dashboard-btn:hover { background-color: #007b43; color: white; }
    .btn-delete { background-color: #d63333; color: white; border-radius: 30px; padding: 3px 10px; margin-left: 5px; }
    .btn-edit { background-color: #007bff; color: white; border-radius: 30px; padding: 3px 10px; }
  </style>
</head>

<body class="py-4">
<div class="container">

  <!-- Header -->
  <div class="text-center mb-4">
    <img src="logo1.png" alt="ScholarSync Global Visa" class="brand-logo">
    <h2 class="text-success fw-bold">ScholarSync Global Company</h2>
    <p class="text-muted mb-0">STARFISH MEMBER RECRUITMENT LIST</p>
  </div>

  <!-- Back to Dashboard -->
  <div class="mb-3 text-end">
    <a href="admin-dashboard.php" class="dashboard-btn">
      <i class="bi bi-arrow-left-circle me-1"></i> Back to Dashboard
    </a>
  </div>

  <!-- Add Member -->
  <div class="card shadow-lg mb-4">
    <div class="card-header">Add New Member</div>
    <div class="card-body p-3">
      <form id="memberForm">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Full Name</label>
            <input type="text" name="fullname" class="form-control form-control-sm" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <label class="form-label">Country</label>
            <select name="country" class="form-select form-select-sm">
              <option value="">Select Country</option>
              <option>USA</option>
              <option>CANADA</option>
              <option>Other</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Membership</label>
            <select name="membership" class="form-select form-select-sm">
              <option value="">Select</option>
              <option>Registered</option>
              <option>Not Registered</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option value="">Select</option>
              <option>Active</option>
              <option>Inactive</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Appointment Date</label>
            <input type="text" id="appointment_date" name="appointment_date" class="form-control form-control-sm">
          </div>
        </div>
        <div class="text-end mt-3">
          <button type="submit" class="btn btn-scholar px-3">
            <i class="bi bi-save2 me-1"></i> Save Member
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Member List -->
  <div class="card shadow-sm">
    <div class="card-header bg-success text-white text-center">Member List</div>
    <div class="card-body">
      <table id="memberTable" class="table table-striped align-middle table-sm">
        <thead>
          <tr>
            <th>#</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Country</th>
            <th>Membership</th>
            <th>Status</th>
            <th>Appointment Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="memberList">
          <tr><td colspan="9" class="text-muted text-center">Loading members...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Edit Member Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editForm">
          <input type="hidden" name="id" id="edit_id">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name</label>
              <input type="text" name="fullname" id="edit_fullname" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="edit_email" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" id="edit_phone" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Country</label>
              <select name="country" id="edit_country" class="form-select">
                <option>USA</option>
                <option>CANADA</option>
                <option>Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Membership</label>
              <select name="membership" id="edit_membership" class="form-select">
                <option>Registered</option>
                <option>Not Registered</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Status</label>
              <select name="status" id="edit_status" class="form-select">
                <option>Active</option>
                <option>Inactive</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Appointment Date</label>
              <input type="text" id="edit_appointment_date" name="appointment_date" class="form-control">
            </div>
          </div>
          <div class="text-end mt-4">
            <button type="submit" class="btn btn-scholar px-4">
              <i class="bi bi-check-circle me-2"></i> Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- JS Logic -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){

  flatpickr("#appointment_date");
  flatpickr("#edit_appointment_date");

  let memberTable;

  function loadMembers(){
    $.get('fetch_members.php', function(data){
      $('#memberList').html(data);
      if ($.fn.DataTable.isDataTable('#memberTable')) memberTable.destroy();

      memberTable = $('#memberTable').DataTable({
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
          {
            extend: 'pdfHtml5',
            text: '<i class="bi bi-file-earmark-pdf-fill me-1"></i> Export PDF',
            className: 'btn btn-danger btn-sm',
            exportOptions: { columns: ':not(:last-child)' },
            title: 'STARFISH MEMBER RECRUITMENT LIST',
            customize: function (doc) {
              doc.defaultStyle.fontSize = 9;
              doc.styles.tableHeader.fontSize = 10;
              doc.content[1].table.widths = ['5%', '20%', '20%', '15%', '10%', '10%', '10%', '10%'];
            }
          },
          {
            extend: 'excelHtml5',
            text: '<i class="bi bi-file-earmark-excel-fill me-1"></i> Export Excel',
            className: 'btn btn-success btn-sm',
            exportOptions: { columns: ':not(:last-child)' },
            title: 'STARFISH MEMBER RECRUITMENT LIST'
          },
          {
            extend: 'print',
            text: '<i class="bi bi-printer-fill me-1"></i> Print',
            className: 'btn btn-secondary btn-sm',
            exportOptions: { columns: ':not(:last-child)' },
            title: 'STARFISH MEMBER RECRUITMENT LIST'
          }
        ],
        order: [[0, 'asc']]
      });
    });
  }
  loadMembers();

  $('#memberForm').on('submit', function(e){
    e.preventDefault();
    $.post('save_member.php', $(this).serialize(), function(response){
      if(response.trim() === 'success'){
        Swal.fire({ icon:'success', title:'Member Added!', timer:1500, showConfirmButton:false });
        $('#memberForm')[0].reset();
        loadMembers();
      }
    });
  });

  $(document).on('click', '.deleteMember', function(){
    let id = $(this).data('id');
    Swal.fire({
      title: 'Delete this member?',
      icon: 'warning',
      showCancelButton: true
    }).then((result)=>{
      if(result.isConfirmed){
        $.post('delete_member.php', {id:id}, ()=>loadMembers());
      }
    });
  });

  $(document).on('click', '.editMember', function(){
    let id = $(this).data('id');
    $.get('get_member.php', {id:id}, function(data){
      let member = JSON.parse(data);
      $('#edit_id').val(member.id);
      $('#edit_fullname').val(member.fullname);
      $('#edit_email').val(member.email);
      $('#edit_phone').val(member.phone);
      $('#edit_country').val(member.country);
      $('#edit_membership').val(member.membership);
      $('#edit_status').val(member.status);
      $('#edit_appointment_date').val(member.appointment_date);
      new bootstrap.Modal(document.getElementById('editModal')).show();
    });
  });

  $('#editForm').on('submit', function(e){
    e.preventDefault();
    $.post('update_member.php', $(this).serialize(), function(response){
      if(response.trim() === 'success'){
        Swal.fire({ icon:'success', title:'Updated!', timer:1500, showConfirmButton:false });
        $('#editModal').modal('hide');
        loadMembers();
      }
    });
  });

});
</script>
</body>
</html>
