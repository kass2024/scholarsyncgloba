<!-- Profile Modal -->
<?php if (!function_exists('pcvc_profile_photo_url')) { require_once __DIR__ . '/helpers/secure_file.php'; } ?>
<div class="modal fade" id="profileModal" tabindex="-1" aria-labelledby="profileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <form action="profile_update.php" method="POST" enctype="multipart/form-data" class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="profileModalLabel">
          <i class="bi bi-person-circle me-2"></i>My Profile
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <!-- Profile Photo -->
        <div class="text-center mb-4">
          <img src="<?= htmlspecialchars(pcvc_profile_photo_url($admin['profile_photo'] ?? '')) ?>"
               class="rounded-circle mb-2"
               style="width:90px;height:90px;object-fit:cover;">
          <input type="file" name="profile_photo" class="form-control mt-2"
                 accept="image/*,.jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.heic,.heif,.avif,.jfif,.svg,.ico">
          <div class="form-text">Any picture format, up to 15MB.</div>
        </div>

        <div class="row g-3">

          <!-- Names -->
          <div class="col-md-6">
            <label class="form-label">First Name</label>
            <input type="text" name="first_name" class="form-control"
                   value="<?= htmlspecialchars($admin['first_name'] ?? '') ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Last Name</label>
            <input type="text" name="last_name" class="form-control"
                   value="<?= htmlspecialchars($admin['last_name'] ?? '') ?>" required>
          </div>

          <div class="col-md-12">
            <label class="form-label">Full Name (auto)</label>
            <input type="text" class="form-control" readonly
                   value="<?= htmlspecialchars($admin['full_name'] ?? '') ?>">
          </div>

          <!-- Contact -->
          <div class="col-md-6">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone_number" class="form-control"
                   value="<?= htmlspecialchars($admin['phone_number'] ?? '') ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control"
                   value="<?= htmlspecialchars($admin['email'] ?? '') ?>" required>
          </div>

          <!-- Identity -->
          <div class="col-md-6">
            <label class="form-label">National ID</label>
            <input type="text" name="national_id" class="form-control"
                   value="<?= htmlspecialchars($admin['national_id'] ?? '') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="date_of_birth" class="form-control"
                   value="<?= htmlspecialchars($admin['date_of_birth'] ?? '') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Marital Status</label>
            <select name="marital_status" class="form-select">
              <option value="">-- Select --</option>
              <?php foreach (['Single','Married','Divorced','Widowed'] as $status): ?>
                <option value="<?= $status ?>" <?= ($admin['marital_status'] ?? '') === $status ? 'selected' : '' ?>>
                  <?= $status ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Nationality</label>
            <input type="text" name="nationality" class="form-control"
                   value="<?= htmlspecialchars($admin['nationality'] ?? '') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Place of Birth</label>
            <input type="text" name="place_of_birth" class="form-control"
                   value="<?= htmlspecialchars($admin['place_of_birth'] ?? '') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Current Residence</label>
            <input type="text" name="address" class="form-control"
                   value="<?= htmlspecialchars($admin['address'] ?? '') ?>">
          </div>

          <!-- Employment -->
          <div class="col-md-6">
            <label class="form-label">Position</label>
            <input type="text" name="position" class="form-control"
                   value="<?= htmlspecialchars($admin['position'] ?? '') ?>">
          </div>

          <div class="col-md-6">
            <label class="form-label">Employment Type</label>
            <select name="employment_type" class="form-select">
              <?php foreach (['Full-time','Part-time','Contract'] as $type): ?>
                <option value="<?= $type ?>" <?= ($admin['employment_type'] ?? '') === $type ? 'selected' : '' ?>>
                  <?= $type ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label">Employment Start Date</label>
            <input type="date" name="employment_start_date" class="form-control"
                   value="<?= htmlspecialchars($admin['employment_start_date'] ?? '') ?>">
          </div>

        </div>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-success">
          <i class="bi bi-save me-1"></i>Update Profile
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>

    </form>
  </div>
</div>
