<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">

  <!-- ⭐ Required for proper mobile + desktop scaling -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Schools Management | ScholarSync Global Visa</title>

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
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <style>
    body {
      background: linear-gradient(135deg, #f3faf4, #ffffff);
      font-family: 'Poppins', sans-serif;
      font-size: 1rem;
    }

    .container {
      max-width: 1280px !important;
    }

    .brand-logo { width: 100px; display: block; margin: 0 auto 10px; }

    .btn-scholar {
      background-color: #004d25; 
      color: white; 
      font-weight: 500;
      border: none; 
      border-radius: 30px; 
      font-size: 1rem;
    }
    .btn-scholar:hover { background-color: #007b43; }

    .card { border: none; border-radius: 15px; }
    .card-header {
      background: #006b3c; 
      color: white; 
      font-weight: 600;
      text-align: center; 
      font-size: 1.2rem;
    }

    table.dataTable thead { 
      background-color: #004d25; 
      color: #fff; 
      font-size: 1rem; 
    }
    table.dataTable td, table.dataTable th {
      vertical-align: middle; 
      font-size: 1rem;
      color: #333;
    }

    .dashboard-btn {
      background-color: #004d25; 
      color: white; 
      border-radius: 30px;
      font-weight: 500; 
      padding: 6px 18px; 
      font-size: 1rem;
    }

    .dashboard-btn:hover { background-color: #007b43; }

    .btn-delete { background-color: #d63333; color: white; border-radius: 30px; padding: 3px 10px; }
    .btn-edit { background-color: #007bff; color: white; border-radius: 30px; padding: 3px 10px; }

    .table-responsive {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    @media(max-width: 768px){
      .card-header { font-size: 1rem; }
      .btn-scholar, .dashboard-btn { font-size: 0.95rem; }
      table.dataTable td, table.dataTable th { font-size: 0.95rem !important; }
    }
  </style>

</head>

<body class="py-4">

<div class="container">

  <!-- Header -->
  <div class="text-center mb-4">
    <img src="logo1.png" alt="ScholarSync Global Visa" class="brand-logo">
    <h2 class="text-success fw-bold">ScholarSync Global Company</h2>
    <p class="text-muted mb-0">Schools & Universities Partnership Management</p>
  </div>

  <!-- Back to Dashboard -->
  <div class="mb-3 text-end">
    <a href="admin-dashboard.php" class="dashboard-btn">
      <i class="bi bi-arrow-left-circle me-1"></i> Back to Dashboard
    </a>
  </div>

  <!-- Add School -->
  <div class="card shadow-lg mb-4">
    <div class="card-header">Add New School</div>
    <div class="card-body p-3">
      <form id="schoolForm">
        <div class="row g-2">

          <div class="col-md-6">
            <label class="form-label">School Name</label>
            <input type="text" name="school_name" class="form-control form-control-sm" required>
          </div>

          <!-- ⭐ WEBSITE ADDED -->
          <div class="col-md-6">
            <label class="form-label">School Website</label>
            <input type="text" name="school_website" class="form-control form-control-sm" placeholder="https://example.com">
          </div>

          <div class="col-md-4">
            <label class="form-label">Category</label>
            <select name="category" class="form-select form-select-sm">
              <option>High School</option>
              <option>Public University</option>
              <option>Rwanda Polytechnic</option>
              <option>Private University</option>
              <option>Abroad</option>
            </select>
          </div>

          <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select form-select-sm">
              <option>TARGETED</option>
              <option>REQUESTED</option>
              <option>APPROVED</option>
            </select>
          </div>

        </div>

        <div class="text-end mt-3">
          <button type="submit" class="btn btn-scholar px-3">
            <i class="bi bi-save2 me-1"></i> Save School
          </button>
        </div>

      </form>
    </div>
  </div>

  <!-- School List -->
  <div class="card shadow-sm">
    <div class="card-header bg-success text-white text-center">School List</div>

    <div class="card-body table-responsive">
      <table id="schoolTable" class="table table-striped align-middle table-sm">
        <thead>
          <tr>
            <th>#</th>
            <th>School Name</th>

            <!-- ⭐ WEBSITE COLUMN ADDED -->
            <th>Website</th>

            <th>Category</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody id="schoolList">
          <tr><td colspan="6" class="text-muted text-center">Loading...</td></tr>
        </tbody>
      </table>
    </div>

  </div>

</div> <!-- END container -->

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header bg-success text-white">
        <h5 class="modal-title">Edit School</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <form id="editForm">

          <input type="hidden" name="id" id="edit_id">

          <div class="mb-3">
            <label class="form-label">School Name</label>
            <input type="text" name="school_name" id="edit_school_name" class="form-control">
          </div>

          <!-- ⭐ WEBSITE FIELD IN EDIT MODAL -->
          <div class="mb-3">
            <label class="form-label">School Website</label>
            <input type="text" name="school_website" id="edit_school_website" class="form-control">
          </div>

          <div class="mb-3">
            <label class="form-label">Category</label>
            <select name="category" id="edit_category" class="form-select">
              <option>High School</option>
              <option>Public University</option>
              <option>Rwanda Polytechnic</option>
              <option>Private University</option>
              <option>Abroad</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" id="edit_status" class="form-select">
              <option>TARGETED</option>
              <option>REQUESTED</option>
              <option>APPROVED</option>
            </select>
          </div>

          <div class="text-end">
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

  let schoolTable;

  function loadSchools(){
    $.get('fetch_schools.php', function(data){
      $('#schoolList').html(data);

      if ($.fn.DataTable.isDataTable('#schoolTable'))
        schoolTable.destroy();

      schoolTable = $('#schoolTable').DataTable({
        responsive: true,
        dom: 'Bfrtip',
        buttons: [
          {
            extend: 'pdfHtml5',
            text: '<i class="bi bi-file-earmark-pdf-fill me-1"></i> PDF',
            className: 'btn btn-danger btn-sm',
            title: 'Schools List',
            exportOptions: { columns: ':not(:last-child)' }
          },
          {
            extend: 'excelHtml5',
            text: '<i class="bi bi-file-earmark-excel-fill me-1"></i> Excel',
            className: 'btn btn-success btn-sm',
            title: 'Schools List',
            exportOptions: { columns: ':not(:last-child)' }
          }
        ],
        order: [[0, 'asc']]
      });

    });
  }

  loadSchools();

  $('#schoolForm').submit(function(e){
    e.preventDefault();
    $.post('save_school.php', $(this).serialize(), function(response){
      if(response.trim() === 'success'){
        Swal.fire({ icon:'success', title:'School Added!', timer:1500, showConfirmButton:false });
        $('#schoolForm')[0].reset();
        loadSchools();
      }
    });
  });

  $(document).on('click', '.editSchool', function(){
    let id = $(this).data('id');

    $.get('get_school.php', {id:id}, function(data){
      let school = JSON.parse(data);

      $('#edit_id').val(school.id);
      $('#edit_school_name').val(school.school_name);

      // ⭐ LOAD WEBSITE
      $('#edit_school_website').val(school.school_website);

      $('#edit_category').val(school.category);
      $('#edit_status').val(school.status);

      new bootstrap.Modal(document.getElementById('editModal')).show();
    });
  });

  $('#editForm').submit(function(e){
    e.preventDefault();
    $.post('update_school.php', $(this).serialize(), function(response){
      if(response.trim() === 'success'){
        Swal.fire({ icon:'success', title:'Updated!', timer:1500, showConfirmButton:false });
        $('#editModal').modal('hide');
        loadSchools();
      }
    });
  });

  $(document).on('click', '.deleteSchool', function(){
    let id = $(this).data('id');

    Swal.fire({
      icon: 'warning',
      title: 'Delete this school?',
      showCancelButton: true
    }).then((result)=>{
      if(result.isConfirmed){
        $.post('delete_school.php', {id:id}, function(){
          loadSchools();
        });
      }
    });
  });

});
</script>

</body>
</html>
