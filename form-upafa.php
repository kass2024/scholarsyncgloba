<?php
require_once __DIR__ . '/includes/brand_logo.php';
require_once __DIR__ . '/helpers/credit_transfer_programs.php';
$brandLogo = scholarsync_brand_logo_href(__DIR__);
$upafaPrograms = pcvc_credit_transfer_programs_flat()['UPAFA'] ?? [];
$upafaCatalogueRel = pcvc_credit_transfer_catalogue_path('UPAFA');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>U.P.A.F.A. Registration Form</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">
  <style>
    :root{ --ink:#222; --brand:#6a2bc2; --brand2:#4b1aa1; --grid:#000; --muted:#6b7280; }
    body{ background:#fff; color:var(--ink); }

    /* PAGE FRAME */
    .page{ max-width:900px; margin:24px auto; border:1px solid #d9d9d9; box-shadow:0 6px 18px rgba(0,0,0,.06); }
    .page-inner{ padding:18px 22px 10px; }
    .thin-rule{ border-top:2px solid #8e8e8e; margin:6px 0 0; }

    /* HEADER */
    .gov-left{ font-size:.9rem; line-height:1.2; }
    .gov-left small{ display:block; color:#666 }
    .logo-wrap{ text-align:center }
    .logo{ height:84px; width:auto; max-width:180px; object-fit:contain }
    .arabic-right{ text-align:right; font-size:.95rem; line-height:1.2 }

    .uni-row{ display:flex; align-items:center; justify-content:space-between; gap:8px; margin-top:6px }
    .uni-left{ font-weight:600; font-size:1.05rem }
    .uni-right{ font-family:"Amiri", serif; font-size:1.05rem }

    /* Title + Academic year box */
    .title-row{ position:relative; display:flex; justify-content:center; align-items:center; margin:14px 0 10px; gap:8px; }
    .reg-title{ color:var(--brand); font-weight:800; letter-spacing:.5px; }
    .year-box{ position:absolute; right:0; border:1px solid #000; padding:6px 10px; font-size:.9rem; background:#fff }

    /* Grid table form */
    .grid{ border:2px solid var(--grid); }
    .grid .r{ display:grid; grid-template-columns:1fr 1fr; border-top:1px solid var(--grid) }
    .grid .r:first-child{ border-top:0 }
    .grid label{ font-weight:600; font-size:.92rem; margin:0; }
    .cell{ display:flex; gap:.5rem; align-items:center; padding:6px 8px; border-right:1px solid var(--grid) }
    .cell:nth-child(2n){ border-right:0 }
    .cell .form-control, .cell .form-select{ height:34px; padding:.25rem .5rem; font-size:.92rem }
    .cell .d-flex > *{ flex:1 1 0 }

    /* Photo box */
    .photo-cell{ grid-column:1 / span 1; display:flex; align-items:center; justify-content:center; }
    .photo-box{ width:95px; height:95px; border:1px solid var(--grid); display:flex; align-items:center; justify-content:center; font-weight:600 }

    /* Inline Y/N */
    .inline-yn{ display:flex; gap:12px; align-items:center; flex-wrap:wrap }

    /* Section Headings inside grid */
    .section-head{ grid-column:1 / span 2; padding:8px 10px; background:linear-gradient(90deg, #f4efff, #ffffff); border-bottom:1px solid var(--grid); display:flex; align-items:center; justify-content:space-between; }
    .section-title{ margin:0; font-size:1rem; font-weight:800; color:var(--brand2); letter-spacing:.4px; text-transform:uppercase }
    .section-sub{ color:var(--muted); font-weight:600; font-size:.85rem }

    /* Commitment */
    .commit-title{ color:var(--brand); font-weight:800; text-align:center; margin:18px 0 10px; font-size:1.3rem }
    .commit-box{ border:2px dashed var(--brand); padding:12px; border-radius:10px; background:#faf8ff; }

    /* Footer */
    .footer{ border-top:3px solid #8e8e8e; margin-top:12px; padding:10px 12px 16px; font-size:.84rem; text-align:center }
    .footer .bar{ font-weight:700; color:#6b6b6b; letter-spacing:.4px }
    .footer a{ color:#0d6efd; text-decoration:underline }

    /* Pills for chosen files */
    .file-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .55rem;border-radius:999px;background:#eef1ff;margin:.25rem .25rem 0 0}

    /* Buttons */
    .btn-primary{ background:linear-gradient(180deg,#7a3be6,#5b23d6); border-color:#5b23d6 }
    .btn-primary:hover{ filter:brightness(.96) }

    /* RESPONSIVE */
    @media (max-width: 768px){
      .page{ max-width:100%; margin:8px; }
      .arabic-right{ text-align:left }
      .uni-row{ flex-direction:column; align-items:flex-start }
      .title-row{ flex-direction:column; align-items:flex-start }
      .year-box{ position:static; margin-left:auto }
      .grid .r{ grid-template-columns:1fr } /* stack rows */
      .cell{ border-right:0 }
      .section-head{ grid-column:1 / span 1 }
    }

    @media print{
      .page{ box-shadow:none; border:0; }
      .btn, .progress, .alert{ display:none !important }
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="page-inner">
      <!-- Header -->
      <div class="row g-2 align-items-start">
        <div class="col-12 col-md-5 gov-left">
          <div><strong>REPUBLIQUE DU Mali</strong></div>
          <small>Un peuple. Un but. Une foi</small>
          <div class="mt-1"><strong>Ministère de l’Enseignements</strong></div>
          <div>Supérieur et de la Recherche Scientifique</div>
        </div>
        <div class="col-12 col-md-2 logo-wrap">
          <img class="logo" src="<?php echo htmlspecialchars($brandLogo); ?>" alt="ScholarSync Global" decoding="async" />
        </div>
        <div class="col-12 col-md-5 arabic-right">
          <div>مالي الجمهورية</div>
          <div>شعب واحد، هدف واحد، إيمان واحد</div>
          <div class="mt-1">وزارة التعليم العالي والبحث العلمي</div>
        </div>
      </div>
      <div class="uni-row">
        <div class="uni-left">Université Privée Africaine Franco-Arabe</div>
        <div class="uni-right">الجامعة العربية الإفريقية الخاصة الأهلية</div>
      </div>
      <div class="thin-rule"></div>

      <!-- Title -->
      <div class="title-row">
        <h2 class="reg-title h5 m-0">REGISTRATION FORM</h2>
        <div class="year-box">
          <label for="academic_year" class="me-1 mb-0 fw-semibold">Academic Year:</label>
          <input
            type="text"
            class="form-control form-control-sm d-inline-block"
            style="width:140px"
            name="academic_year"
            id="academic_year"
            value="2024/2025"
            placeholder="2024/2025 or 2024 - 2025"
            form="registrationForm"
            required
            pattern="^\s*\d{4}\s*[-/]\s*\d{4}\s*$"
            oninvalid="this.setCustomValidity('Use format: 2024/2025 or 2024 - 2025')"
            oninput="this.setCustomValidity('')"
          />
        </div>
      </div>

      <!-- Form -->
      <form id="registrationForm" class="needs-validation" novalidate enctype="multipart/form-data">
        <input type="hidden" name="academic_year" id="academic_year_hidden">

        <div class="grid">
          <!-- Row 1 -->
          <div class="r">
            <div class="cell photo-cell">
              <div>
                <label class="form-label d-block text-center">Photo</label>
                <div class="photo-box mb-2" id="photo_frame">Photo</div>
                <input class="form-control form-control-sm" type="file" accept="image/*" id="passport_photo" name="passport_photo" required>
              </div>
            </div>
            <div class="cell">
              <label class="me-2">Last Name</label>
              <input class="form-control" name="last_name" required>
            </div>
          </div>

          <!-- Row 2 -->
          <div class="r">
            <div class="cell">
              <label class="me-2">First Name</label>
              <input class="form-control" name="first_name" required>
            </div>
            <div class="cell">
              <label class="me-2">Nationality</label>
              <input class="form-control" name="nationality" required>
            </div>
          </div>

          <!-- Row 3 -->
          <div class="r">
            <div class="cell">
              <label class="me-2">Place and Date of Birth</label>
              <div class="d-flex gap-2 flex-grow-1">
                <input class="form-control" name="birth_place" placeholder="City, Country" required>
                <input type="date" class="form-control" name="birth_date" required>
              </div>
            </div>
            <div class="cell">
              <label class="me-2">The highest Education level</label>
              <select class="form-select" name="highest_education" required>
                <option value="" selected disabled>Choose…</option>
                <option>High School</option>
                <option>Diploma</option>
                <option>Bachelor</option>
                <option>Master</option>
                <option>PhD</option>
              </select>
            </div>
          </div>

          <!-- Row 4 -->
          <div class="r">
            <div class="cell">
              <label class="me-2">Departement</label>
              <input class="form-control" name="department" required>
            </div>
            <div class="cell">
              <label class="me-2">Attended School Name & Address</label>
              <input class="form-control" name="school_name_address" required>
            </div>
          </div>

          <!-- Row 5 -->
          <div class="r">
            <div class="cell">
              <label class="me-2">Year attended</label>
              <div class="d-flex gap-2 flex-grow-1">
                <input type="number" min="1950" max="2100" class="form-control" name="year_from" placeholder="From" required>
                <input type="number" min="1950" max="2100" class="form-control" name="year_to" placeholder="To" required>
              </div>
            </div>
            <div class="cell">
              <label class="me-2">I intend to study towards a degree</label>
              <div class="d-flex gap-2 flex-grow-1">
                <select class="form-select" name="intended_degree" id="intended_degree" required>
                  <option value="" selected disabled>Select degree…</option>
                  <option value="Bachelor">Bachelor</option>
                  <option value="Master">Master</option>
                  <option value="PhD">PhD</option>
                </select>
                <input class="form-control" name="field_of_study" id="field_of_study"
                       list="field_of_study_list" placeholder="Start typing a field…" required>
                <datalist id="field_of_study_list"></datalist>
              </div>
              <?php if ($upafaCatalogueRel): ?>
                <a class="small ms-2" href="<?= htmlspecialchars($upafaCatalogueRel, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">UPAFA 2025 program catalogue (PDF)</a>
              <?php endif; ?>
            </div>
          </div>

          <!-- ===== FEES SECTION HEADING (replaces the old input) ===== -->
          <div class="r">
            <div class="section-head">
              <h3 class="section-title mb-0">Credit Transfer Money</h3>
              <div class="section-sub">Enter applicable fees below (if not applicable, put 0)</div>
            </div>
          </div>

          <!-- Fees row -->
          <div class="r">
            <div class="cell">
              <label class="me-2">Registration Fees</label>
              <input type="number" min="0" class="form-control" name="registration_fees" required>
            </div>
            <div class="cell">
              <label class="me-2">Tuition Fees</label>
              <input type="number" min="0" class="form-control" name="tuition_fees" required>
            </div>
          </div>
          <!-- ===== END FEES SECTION ===== -->

          <!-- Row 7 -->
          <div class="r">
            <div class="cell">
              <label class="me-2">Do you have a scholarship?</label>
              <div class="inline-yn">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="scholarship" id="sch_yes" value="Yes">
                  <label class="form-check-label" for="sch_yes">Yes</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="scholarship" id="sch_no" value="No" checked>
                  <label class="form-check-label" for="sch_no">No</label>
                </div>
                <input type="text" class="form-control d-none" id="scholarship_institution" name="scholarship_institution" placeholder="If Yes: From which Institution?">
              </div>
            </div>
            <div class="cell">
              <label class="me-2">Referred by Partner ScholarSync Global Co. Ltd?</label>
              <div class="inline-yn">
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="referred_by_scholarsync" id="ref_yes" value="Yes">
                  <label class="form-check-label" for="ref_yes">Yes</label>
                </div>
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio" name="referred_by_scholarsync" id="ref_no" value="No" checked>
                  <label class="form-check-label" for="ref_no">No</label>
                </div>
                <input type="text" class="form-control d-none" id="ref_institution" name="ref_institution" placeholder="If Yes: From which Institution ?">
              </div>
            </div>
          </div>

          <!-- Row 8 -->
          <div class="r">
            <div class="cell">
              <label class="me-2">Telephone</label>
              <input class="form-control" name="telephone" required>
            </div>
            <div class="cell">
              <label class="me-2">E-mail</label>
              <input type="email" class="form-control" name="email" required>
            </div>
          </div>
        </div>

        <!-- Documents -->
        <div class="mt-3">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Passport or ID</label>
              <input class="form-control" type="file" accept="image/*,.pdf" id="id_document" name="id_document" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Certificate of Birth</label>
              <input class="form-control" type="file" accept="image/*,.pdf" id="birth_certificate" name="birth_certificate" required>
            </div>

            <div class="col-md-6">
              <label class="form-label fw-semibold">Last Degree (single file)</label>
              <input class="form-control" type="file" accept="image/*,.pdf" id="last_degree" name="last_degree">
              <div id="last_degree_pill" class="mt-1"></div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Transcript (single file)</label>
              <input class="form-control" type="file" accept="image/*,.pdf" id="transcript_file" name="transcript_file">
              <div id="transcript_file_pill" class="mt-1"></div>
            </div>

            <div class="col-12">
              <label class="form-label">Other Attachments (optional)</label>
              <input class="form-control" type="file" accept="image/*,.pdf" id="other_attachments" name="other_attachments[]" multiple>
              <div id="other_attachments_list" class="mt-1"></div>
            </div>
          </div>
        </div>

        <!-- Commitment -->
        <div class="commit-title">COMMITMENT</div>
        <div class="commit-box">
          <p class="mb-2">
            I, the undersigned, Mr. / Mrs.
            <input type="text" class="form-control d-inline-block" name="commitment_name"
              placeholder="Your Full Name" style="width:280px; margin-left:6px; margin-right:6px;" required>
          </p>
          <p class="mb-2">
            Undertake to respect the Internal Regulations of
            <strong>Université Privée Africaine Franco-Arabe (U.P.A.F.A.)</strong>
            and to renounce any behavior that hinders the achievement of the University's objectives;
          </p>
          <p class="mb-2">
            In the event of failure on my side, the administrative authorities of my host establishment will take the regulatory sanctions in force against me.
          </p>
          <p class="mb-2">In witness, I confirm that the given information is right.</p>
          <div class="row g-3 align-items-center">
            <div class="col-md-5">
              <label class="form-label">Done at</label>
              <input class="form-control" name="done_at" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">on</label>
              <input type="date" class="form-control" name="done_date" required>
            </div>
            <div class="col-md-3 text-md-end fw-bold">CENTRALE SECRETARIAT</div>
          </div>
          <div class="mt-3"><em>(Applicant Signature)</em></div>
        </div>

        <!-- Actions / Alerts -->
        <div class="text-end mt-3">
          <button class="btn btn-primary">Submit Registration</button>
        </div>
        <div class="progress mt-2 d-none" id="upload_progress_wrap">
          <div class="progress-bar progress-bar-striped progress-bar-animated" id="upload_progress" role="progressbar" style="width:0%"></div>
        </div>
        <div class="alert alert-success mt-2 d-none" id="success_alert">Submitted successfully.</div>
        <div class="alert alert-danger mt-2 d-none" id="error_alert">Submission failed. Please try again.</div>
      </form>

      <!-- Footer -->
      <div class="footer">
        <div class="bar">REGISTRATION SERVICE</div>
        <div>Adresse : Quartier Marsellie après Boullassougou, ancien Lycée Privé Tata Traoré LPTTS situé de la Mosquée de Vendredi. Bamako –</div>
        <div>MAIL Tel.: 00223 - 74979607 - 77715824</div>
        <div>
          <a href="#">universiteafricaine.francoarabe@gmail.com</a>,
          <a href="#">register@edu-upafa.com</a>,
          <a href="#">info@edu-upafa.com</a>,
          web: <a href="#">www.edu-upafa.com</a>
        </div>
        <div class="mt-1">DECISION: N°2018-002497/MEN-SG DU 16 OCT 2018 - ARRETE: N°1771A/MENESRS-SG</div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
  (() => {
    // helpers
    const $  = (s, r=document) => r.querySelector(s);
    const $$ = (s, r=document) => [...r.querySelectorAll(s)];
    const fmtBytes = b => (b<1024? b+' B' : b<1048576? (b/1024).toFixed(1)+' KB' : (b/1048576).toFixed(2)+' MB');

    // academic year mirror
    const yearExternal = $('#academic_year');
    const yearHidden   = $('#academic_year_hidden');
    const syncYear = () => { yearHidden.value = (yearExternal.value || '').trim(); };
    yearExternal.addEventListener('input',  syncYear);
    yearExternal.addEventListener('change', syncYear);
    syncYear();

    // radios -> extra inputs
    const toggleByRadio = (name, inputId) => {
      const extra = $('#'+inputId);
      const update = () => {
        const yes = $(`input[name="${name}"][value="Yes"]`);
        extra.classList.toggle('d-none', !(yes && yes.checked));
        if (!(yes && yes.checked)) extra.value = '';
      };
      $$(`input[name="${name}"]`).forEach(r => r.addEventListener('change', update));
      update();
    };
    toggleByRadio('scholarship', 'scholarship_institution');
    toggleByRadio('referred_by_scholarsync', 'ref_institution');

    // file “pill” helper (single)
    const pillSingle = (input, mount) => {
      input.addEventListener('change', () => {
        mount.innerHTML = '';
        const f = input.files && input.files[0];
        if (!f) return;
        const s = document.createElement('span');
        s.className = 'file-pill';
        s.textContent = `${f.name} • ${fmtBytes(f.size)}`;
        mount.appendChild(s);
      });
    };

    // file “pills” (multi)
    const pillList = (input, mount) => {
      input.addEventListener('change', () => {
        mount.innerHTML = '';
        [...(input.files || [])].forEach(f => {
          const s = document.createElement('span');
          s.className = 'file-pill';
          s.textContent = `${f.name} • ${fmtBytes(f.size)}`;
          mount.appendChild(s);
        });
      });
    };

    pillSingle($('#last_degree'), $('#last_degree_pill'));
    pillSingle($('#transcript_file'), $('#transcript_file_pill'));
    pillList($('#other_attachments'),  $('#other_attachments_list'));

    // photo preview
    const pp = $('#passport_photo'), frame = $('#photo_frame');
    pp.addEventListener('change', () => {
      const f = pp.files?.[0]; if (!f) return;
      const url = URL.createObjectURL(f);
      frame.style.background = `center/cover no-repeat url(${url})`;
      frame.textContent = '';
    });

    // submit
    const form = $('#registrationForm');
    form.addEventListener('submit', (e) => {
      e.preventDefault(); e.stopPropagation();
      syncYear();

      if (!form.checkValidity()) {
        const invalid = $$(':invalid', form);
        invalid[0]?.reportValidity?.();
        form.classList.add('was-validated');
        return;
      }

      const t0 = performance.now();
      const fd = new FormData(form);

      const lastDeg = $('#last_degree');
      const trans   = $('#transcript_file');
      if (lastDeg?.files?.[0]) { fd.set('last_degree', lastDeg.files[0]); }
      if (trans?.files?.[0])   { fd.set('transcript_file', trans.files[0]); }

      const other = $('#other_attachments');
      if (other) {
        fd.delete('other_attachments[]');
        for (const f of (other.files || [])) fd.append('other_attachments[]', f);
      }

      // UI
      const wrap = $('#upload_progress_wrap');
      const bar  = $('#upload_progress');
      const ok   = $('#success_alert');
      const err  = $('#error_alert');
      ok.classList.add('d-none'); err.classList.add('d-none'); wrap.classList.remove('d-none');
      bar.style.width = '0%'; bar.textContent = '';

      const xhr = new XMLHttpRequest();

      xhr.upload.addEventListener('progress', ev => {
        if (!ev.lengthComputable) return;
        const pct = Math.round((ev.loaded / ev.total) * 100);
        bar.style.width = pct + '%'; bar.textContent = pct + '%';
      });

      xhr.onerror = () => {
        wrap.classList.add('d-none'); err.classList.remove('d-none');
      };

      xhr.onreadystatechange = () => {
        if (xhr.readyState !== 4) return;
        wrap.classList.add('d-none');

        if (xhr.status >= 200 && xhr.status < 300) {
          ok.classList.remove('d-none');
          form.reset();
          bar.style.width = '0%'; bar.textContent = '';
          $('#last_degree_pill').innerHTML = '';
          $('#transcript_file_pill').innerHTML = '';
          $('#other_attachments_list').innerHTML = '';
          const frame = $('#photo_frame');
          frame.style.background = ''; frame.textContent = 'Photo';
          form.classList.remove('was-validated');
          return;
        }

        const body = (xhr.responseText || '').toString().trim();
        err.innerHTML = body || 'Submission failed. Please try again.';
        err.classList.remove('d-none');
      };

      xhr.open('POST', 'save-form-upafa.php'); // your PHP endpoint
      xhr.send(fd);
    });
  })();
  </script>

  <!-- Field-of-Study options -->
  <script>
    (function(){
      const fields = <?= json_encode(array_values($upafaPrograms), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      const list = document.getElementById('field_of_study_list');
      fields.forEach(txt => {
        const opt = document.createElement('option');
        opt.value = txt;
        list.appendChild(opt);
      });
    })();
  </script>
</body>
</html>
