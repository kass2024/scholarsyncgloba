<!-- Change Password Modal -->
<div class="modal fade" id="changePassModal" tabindex="-1" aria-labelledby="changePassModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form action="change_password.php" method="POST" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="changePassModalLabel">Change Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label>New Password:</label>
        <input type="password" name="new_password" class="form-control mb-3" required>

        <label>Confirm Password:</label>
        <input type="password" name="confirm_password" class="form-control mb-3" required>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Change Password</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</div>
