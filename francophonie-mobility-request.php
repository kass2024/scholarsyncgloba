<?php
declare(strict_types=1);

ob_start();
session_name('FM_MOBILITY_FORM');
session_start();

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'fm_' . bin2hex(random_bytes(6)) . '_' . time();
}
$user_id = $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
fm_ensure_schema($conn);

$already = false;
$st = $conn->prepare('SELECT reference_id, status FROM francophonie_mobility_applications WHERE user_id = ? LIMIT 1');
if ($st) {
    $st->bind_param('s', $user_id);
    $st->execute();
    $existing = $st->get_result()->fetch_assoc();
    $st->close();
    if ($existing) {
        $already = true;
        $existing_ref = $existing['reference_id'];
        $existing_status = $existing['status'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Canada Francophonie Mobility — Candidate Form | ScholarSync Global</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/css/intlTelInput.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --fm-green: #1e4d2b;
            --fm-blue: #3661B9;
            --fm-red: #c41e1e;
            --fm-bg: #f4f6f3;
        }
        body { font-family: "Segoe UI", system-ui, sans-serif; background: var(--fm-bg); color: #0f172a; -webkit-text-size-adjust: 100%; }
        .fm-wrap { max-width: 900px; margin: 0 auto; padding: 1rem 0.75rem 2.5rem; }
        .fm-hero {
            background: linear-gradient(135deg, var(--fm-green) 0%, var(--fm-blue) 100%);
            color: #fff; border-radius: 16px; padding: clamp(1.25rem, 4vw, 2rem); margin-bottom: 1.25rem;
        }
        .fm-hero h1 { font-size: clamp(1.2rem, 4.5vw, 1.85rem); font-weight: 700; margin: 0; line-height: 1.3; }
        .fm-hero .sub { opacity: .92; margin-top: .5rem; font-size: clamp(.85rem, 2.5vw, .95rem); }
        .fm-section {
            background: #fff; border-radius: 12px; border: 1px solid #e2e8f0;
            padding: clamp(1rem, 3vw, 1.5rem); margin-bottom: 1rem; box-shadow: 0 2px 12px rgba(30,77,43,.06);
        }
        .fm-section h2 {
            font-size: clamp(1rem, 3vw, 1.1rem); font-weight: 700; color: var(--fm-green);
            margin: 0 0 1rem; padding-bottom: .75rem; border-bottom: 2px solid #e2e8f0;
        }
        .fm-section h2 span { color: var(--fm-red); margin-right: .35rem; }
        .fm-label.required::after { content: " *"; color: var(--fm-red); }
        .upload-zone {
            border: 2px dashed #cbd5e1; border-radius: 10px; padding: 1rem; text-align: center;
            cursor: pointer; transition: .2s; background: #fafafa; min-height: 100px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
        }
        .upload-zone:hover, .upload-zone.dragover { border-color: var(--fm-blue); background: #f0f9ff; }
        .lang-block { background: #f8fafc; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .lang-options { display: flex; flex-wrap: wrap; gap: .5rem 1rem; }
        .btn-fm {
            background: linear-gradient(135deg, var(--fm-green), var(--fm-blue)); border: 0; color: #fff;
            font-weight: 600; width: 100%; max-width: 360px;
        }
        .btn-fm:hover { opacity: .92; color: #fff; }
        #successPanel { display: none; }
        .ref-box {
            font-family: monospace; font-size: clamp(1rem, 4vw, 1.25rem); background: #f1f5f9;
            padding: .75rem 1rem; border-radius: 8px; word-break: break-all;
        }
        .iti { width: 100%; }
        .form-control, .form-select { font-size: 16px; }
        .upload-item {
            display:flex; align-items:center; justify-content:space-between; gap:.5rem;
            padding:.35rem .5rem; background:#f0fdf4; border-radius:6px; margin-top:.35rem;
            font-size:.85rem; word-break:break-all;
        }
        .upload-item .btn-remove { flex-shrink:0; }
        .upload-progress { height: 4px; margin-top: .35rem; }
        #submitProgress .progress { height: 10px; border-radius: 6px; }
        #submitProgress .progress-bar { background: linear-gradient(90deg, var(--fm-green), var(--fm-blue)); }
        .btn-fm:disabled { opacity: .75; }
        @media (max-width: 576px) {
            .fm-wrap { padding-left: 0.65rem; padding-right: 0.65rem; }
            .form-check-inline { display: block; margin-right: 0; margin-bottom: .35rem; }
        }
    </style>
</head>
<body>
<div class="fm-wrap">

<?php if ($already): ?>
    <div class="fm-hero text-center">
        <h1><i class="fas fa-check-circle me-2"></i>Application Already Submitted</h1>
        <p class="sub">Your Francophonie Mobility candidate form is on file.</p>
    </div>
    <div class="fm-section text-center">
        <p class="mb-2">Reference ID</p>
        <div class="ref-box d-inline-block"><?= htmlspecialchars($existing_ref ?? '') ?></div>
        <p class="mt-3 text-muted">Status: <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $existing_status ?? 'pending'))) ?></strong></p>
        <p class="small text-muted">All updates are sent to your email. Contact our office if you need assistance.</p>
        <a href="index.php?card=francophonie" class="btn btn-outline-secondary mt-2">Back to services</a>
    </div>
<?php else: ?>

    <div id="formPanel">
        <div class="fm-hero">
            <h1>WORK VISA IN CANADA — FRANCOPHONIE MOBILITY</h1>
            <p class="sub mb-0">Mobilité Francophone — Candidate Information Form</p>
        </div>

        <div class="alert alert-danger d-none" id="errorBox">
            <strong>Please fix the following:</strong>
            <ul id="errorList" class="mb-0 ps-3"></ul>
        </div>

        <form id="fmForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="user_id" value="<?= htmlspecialchars($user_id) ?>">

            <!-- 1. Personal Information (docx) -->
            <section class="fm-section">
                <h2><span>1.</span> Personal Information</h2>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fm-label required" for="full_name">Full Name</label>
                        <input class="form-control" id="full_name" name="full_name" required maxlength="200" autocomplete="name">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fm-label required" for="date_of_birth">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required autocomplete="bday">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fm-label required" for="passport_number">Passport Number</label>
                        <input class="form-control" id="passport_number" name="passport_number" required maxlength="64" autocomplete="off" placeholder="As shown on passport">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label fm-label" for="age">Age</label>
                        <input type="number" class="form-control" id="age" name="age" min="0" max="150" placeholder="Optional">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fm-label required" for="nationality">Nationality</label>
                        <input class="form-control" id="nationality" name="nationality" required autocomplete="country-name">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fm-label" for="country_of_residence">Current Country of Residence</label>
                        <input class="form-control" id="country_of_residence" name="country_of_residence" autocomplete="country">
                    </div>
                    <div class="col-12">
                        <label class="form-label fm-label required" for="address">Full Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" required maxlength="500" placeholder="Street, city, province/state, postal code, country" autocomplete="street-address"></textarea>
                        <div class="form-text">Required for your service agreement — include your complete residential address.</div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fm-label" for="profession">Current Profession / Occupation</label>
                        <input class="form-control" id="profession" name="profession">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fm-label" for="years_experience">Years of Professional Experience</label>
                        <input class="form-control" id="years_experience" name="years_experience" placeholder="e.g. 5">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fm-label required" for="email">Email (all communication)</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fm-label" for="phone">Phone</label>
                        <input type="tel" class="form-control" id="phone">
                        <input type="hidden" name="phone_area_code" id="phone_area_code">
                        <input type="hidden" name="phone_number" id="phone_number">
                    </div>
                </div>
            </section>

            <!-- 2. Education -->
            <section class="fm-section">
                <h2><span>2.</span> Education Background</h2>
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label fm-label" for="highest_degree">Highest Degree Obtained</label>
                        <input class="form-control" id="highest_degree" name="highest_degree">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fm-label" for="field_of_study">Field of Study</label>
                        <input class="form-control" id="field_of_study" name="field_of_study">
                    </div>
                    <div class="col-12 col-sm-6">
                        <label class="form-label fm-label" for="university_name">University / College Name</label>
                        <input class="form-control" id="university_name" name="university_name">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fm-label" for="country_of_study">Country of Study</label>
                        <input class="form-control" id="country_of_study" name="country_of_study">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label fm-label" for="graduation_year">Graduation Year</label>
                        <input class="form-control" id="graduation_year" name="graduation_year" placeholder="YYYY">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="other_certifications">Other Relevant Certifications or Training (if any)</label>
                        <textarea class="form-control" id="other_certifications" name="other_certifications" rows="2"></textarea>
                    </div>
                </div>
            </section>

            <!-- 3. Languages -->
            <section class="fm-section">
                <h2><span>3.</span> Language Abilities</h2>
                <div class="lang-block">
                    <h3 class="h6 fw-bold text-danger mb-3">French Language</h3>
                    <div class="mb-3">
                        <label class="form-label fm-label">French Level</label>
                        <div class="lang-options">
                            <?php foreach (['beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced','fluent'=>'Fluent'] as $v=>$l): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="french_level" id="fr_<?= $v ?>" value="<?= $v ?>">
                                <label class="form-check-label" for="fr_<?= $v ?>"><?= $l ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Do you have?</label>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="french_tef" id="french_tef" value="1"><label class="form-check-label" for="french_tef">TEF</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="french_tcf" id="french_tcf" value="1"><label class="form-check-label" for="french_tcf">TCF</label></div>
                    </div>
                    <div>
                        <label class="form-label fm-label">Can you work professionally in French?</label>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="french_professional" id="fr_pro_yes" value="yes"><label class="form-check-label" for="fr_pro_yes">Yes</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="french_professional" id="fr_pro_no" value="no"><label class="form-check-label" for="fr_pro_no">No</label></div>
                    </div>
                </div>
                <div class="lang-block mb-0">
                    <h3 class="h6 fw-bold text-primary mb-3">English Language</h3>
                    <div class="mb-3">
                        <label class="form-label fm-label">English Level</label>
                        <div class="lang-options">
                            <?php foreach (['beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced','fluent'=>'Fluent'] as $v=>$l): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="english_level" id="en_<?= $v ?>" value="<?= $v ?>">
                                <label class="form-check-label" for="en_<?= $v ?>"><?= $l ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Do you have?</label>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="english_toefl" id="english_toefl" value="1"><label class="form-check-label" for="english_toefl">TOEFL</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="english_ielts" id="english_ielts" value="1"><label class="form-check-label" for="english_ielts">IELTS</label></div>
                    </div>
                    <div>
                        <label class="form-label fm-label">Can you work professionally in English?</label>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="english_professional" id="en_pro_yes" value="yes"><label class="form-check-label" for="en_pro_yes">Yes</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="english_professional" id="en_pro_no" value="no"><label class="form-check-label" for="en_pro_no">No</label></div>
                    </div>
                </div>
            </section>

            <!-- 4. WES -->
            <section class="fm-section">
                <h2><span>4.</span> Do you have WES?</h2>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="has_wes" id="wes_yes" value="yes"><label class="form-check-label" for="wes_yes">Yes</label></div>
                <div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="has_wes" id="wes_no" value="no"><label class="form-check-label" for="wes_no">No</label></div>
            </section>

            <!-- 5. Job offer -->
            <section class="fm-section">
                <h2><span>5.</span> Canada Available Job Opportunities</h2>
                <p class="small text-muted mb-3">Select the position you are applying for in Canada.</p>
                <div class="mb-2">
                    <label class="form-label fm-label required">Preferred Job Offer</label>
                    <?php $jobNum = 0; foreach (fm_job_offer_choices() as $slug => $label): $jobNum++; ?>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="job_offer" id="job_<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" required>
                        <label class="form-check-label" for="job_<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"><?= $jobNum ?>. <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Attachments (at least one required) -->
            <section class="fm-section">
                <h2><span>6.</span> Attachments <span class="text-danger fw-normal small">(at least one required *)</span></h2>
                <p class="small text-muted mb-3">Upload at least one document — CV, language certificate, or academic file. Each file is saved as soon as it finishes uploading.</p>
                <div class="row g-3">
                    <?php
                    $singleFiles = [
                        'cv' => ['post' => 'cv_file', 'label' => 'CV'],
                        'french_cert' => ['post' => 'french_cert_file', 'label' => 'French Certificate'],
                        'english_cert' => ['post' => 'english_cert_file', 'label' => 'English Certificate (if any)'],
                    ];
                    foreach ($singleFiles as $fid => $meta):
                    ?>
                    <div class="col-12 col-sm-6">
                        <label class="form-label"><?= htmlspecialchars($meta['label']) ?></label>
                        <div class="upload-zone" data-field="<?= $fid ?>" data-post="<?= $meta['post'] ?>">
                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                            <div class="small">Click or drag file (any type)</div>
                            <div class="preview mt-2 small" id="<?= $fid ?>-preview"></div>
                        </div>
                        <input type="file" class="d-none file-input" id="<?= $fid ?>" data-field="<?= $fid ?>">
                        <input type="hidden" name="<?= $meta['post'] ?>" id="<?= $fid ?>_path">
                    </div>
                    <?php endforeach; ?>

                    <div class="col-12">
                        <label class="form-label">Academic Documents</label>
                        <div class="upload-zone" data-field="academic" data-multiple="1">
                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                            <div class="small">Click or drag one or more files (any type)</div>
                            <div class="small text-muted mt-1">You can upload several at once, or add more after each upload.</div>
                            <div class="preview mt-2 small w-100 text-start" id="academic-preview"></div>
                        </div>
                        <input type="file" class="d-none file-input" id="academic" data-field="academic" multiple>
                        <input type="hidden" name="academic_docs_file" id="academic_docs_path" value="">
                        <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="academicAddMore">
                            <i class="fas fa-plus me-1"></i> Add more academic documents
                        </button>
                    </div>
                </div>
            </section>

            <!-- 7. Introduction video -->
            <section class="fm-section">
                <h2><span>7.</span> Introduction Video <span class="text-muted fw-normal small">(recommended · max 1 minute)</span></h2>
                <p class="small text-muted mb-3">
                    Record or upload a self-interview in <strong>English</strong> (French welcome too).
                    Videos go <strong>directly to pCloud</strong> and are <strong>not kept on this server</strong>.
                </p>

                <div class="alert alert-light border mb-3 py-3">
                    <div class="fw-semibold mb-2"><i class="fas fa-list-check me-1 text-danger"></i> Key Points for a 1-Minute Self-Recording Interview Video (English)</div>
                    <p class="small mb-2 text-muted"><strong>Total Length:</strong> 60 seconds</p>
                    <ol class="small mb-2 ps-3">
                        <li class="mb-1"><strong>Introduction</strong> (8–10 seconds) — name, profession/field, years of experience</li>
                        <li class="mb-1"><strong>Education &amp; Qualifications</strong> (8–10 seconds) — highest degree, most relevant certification or training</li>
                        <li class="mb-1"><strong>Professional Experience</strong> (12–15 seconds) — current/recent position, main responsibilities, one key achievement</li>
                        <li class="mb-1"><strong>Key Skills</strong> (8–10 seconds) — technical skills, computer skills, teamwork and problem-solving</li>
                        <li class="mb-1"><strong>French Language Ability</strong> (5–7 seconds) — ability to communicate and work in French</li>
                        <li class="mb-1"><strong>Why Canada &amp; Mobilité Francophone</strong> (7–8 seconds) — career growth, contribute to Canadian employers and Francophone communities</li>
                        <li class="mb-1"><strong>Closing</strong> (5–7 seconds) — why you are a strong candidate (education, experience, French, work ethic); thank the employer and express enthusiasm</li>
                    </ol>
                    <p class="small mb-2 text-muted">
                        <strong>Simple Formula to Remember:</strong><br>
                        <strong>WHO YOU ARE → WHAT YOU STUDIED → WHAT YOU DO → YOUR SKILLS → FRENCH → WHY CANADA → WHY HIRE YOU</strong>
                    </p>
                    <div class="small text-muted mb-0">
                        <strong>Recommended Time Allocation (1 Minute):</strong>
                        Introduction 15% · Education 15% · Experience 25% · Skills 15% · French Ability 10% · Why Canada 10% · Closing 10%
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="videoUploadBtn">
                        <i class="fas fa-upload me-1"></i> Upload video
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="videoRecordBtn">
                        <i class="fas fa-video me-1"></i> Record live
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm d-none" id="videoStopBtn">
                        <i class="fas fa-stop me-1"></i> Stop recording
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm d-none" id="videoClearBtn">
                        <i class="fas fa-trash me-1"></i> Remove
                    </button>
                    <span class="align-self-center small text-muted d-none" id="videoTimerLabel">Recording: <strong id="videoTimer">0:00</strong> / 1:00</span>
                </div>
                <input type="file" id="videoFileInput" class="d-none" accept="video/*,.mp4,.webm,.mov,.m4v">
                <div class="row g-3 align-items-start">
                    <div class="col-md-7">
                        <video id="videoPreview" class="w-100 rounded border bg-dark" style="min-height:200px;max-height:320px" playsinline muted></video>
                        <div class="progress mt-2 d-none" id="videoProgressWrap" style="height:8px">
                            <div class="progress-bar" id="videoProgressBar" style="width:0%"></div>
                        </div>
                        <div class="small text-muted mt-1" id="videoStatus">No video yet — upload a file or start a live recording (auto-stops at 1 minute).</div>
                    </div>
                    <div class="col-md-5">
                        <ul class="small text-muted mb-0 ps-3">
                            <li><strong>Limit: 1 minute maximum (60 seconds)</strong> (live recording auto-stops).</li>
                            <li>Cover all 7 points above — experience should be the longest part.</li>
                            <li>Speak clearly to the camera; good light and quiet room.</li>
                            <li>Max size ~200&nbsp;MB (MP4 / WebM / MOV).</li>
                        </ul>
                    </div>
                </div>
                <input type="hidden" name="video_file" id="video_file" value="">
                <input type="hidden" name="video_source" id="video_source" value="">
                <input type="hidden" name="video_pcloud_fileid" id="video_pcloud_fileid" value="">
                <input type="hidden" name="video_pcloud_link" id="video_pcloud_link" value="">
            </section>

            <div class="text-center mb-4 px-2">
                <div id="submitProgress" class="d-none mx-auto mb-3" style="max-width:420px">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span id="submitProgressLabel">Submitting application…</span>
                        <span id="submitProgressPct">0%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="submitProgressBar" role="progressbar" style="width:0%"></div>
                    </div>
                </div>
                <button type="submit" class="btn btn-fm btn-lg" id="submitBtn">
                    <i class="fas fa-paper-plane me-2"></i> Submit Application
                </button>
            </div>
        </form>
    </div>

    <div id="successPanel" class="fm-section text-center">
        <div class="text-success mb-3" style="font-size:3rem"><i class="fas fa-check-circle"></i></div>
        <h2 class="h4">Application Submitted</h2>
        <p class="text-muted">A confirmation email has been sent. Save your reference ID.</p>
        <div class="ref-box d-inline-block my-3" id="successRef"></div>
        <p class="small text-muted">Our team will review your file and contact you by email.</p>
    </div>

<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/intlTelInput.min.js"></script>
<script>
(function() {
    const form = document.getElementById('fmForm');
    if (!form) return;

    const phoneIti = intlTelInput(document.getElementById('phone'), {
        utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js',
        separateDialCode: true,
        preferredCountries: ['ca', 'rw', 'fr', 'be', 'cd']
    });

    const uploads = { cv: '', french_cert: '', english_cert: '' };
    const academicUploads = []; // { path, name }
    let uploadsInFlight = 0;
    let mediaStream = null;
    let mediaRecorder = null;
    let recordedChunks = [];
    let videoPreviewUrl = '';
    let recordTimer = null;
    let recordSeconds = 0;
    const MAX_RECORD_SECONDS = 60; // 1 minute

    function formatTimer(sec) {
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return m + ':' + String(s).padStart(2, '0');
    }

    function stopRecordTimer() {
        if (recordTimer) {
            clearInterval(recordTimer);
            recordTimer = null;
        }
        const label = document.getElementById('videoTimerLabel');
        if (label) label.classList.add('d-none');
    }

    function startRecordTimer() {
        recordSeconds = 0;
        const label = document.getElementById('videoTimerLabel');
        const timerEl = document.getElementById('videoTimer');
        if (recordTimer) {
            clearInterval(recordTimer);
            recordTimer = null;
        }
        if (label) label.classList.remove('d-none');
        if (timerEl) timerEl.textContent = '0:00';
        recordTimer = setInterval(() => {
            recordSeconds += 1;
            if (timerEl) timerEl.textContent = formatTimer(recordSeconds);
            if (recordSeconds >= MAX_RECORD_SECONDS) {
                document.getElementById('videoStatus').textContent = '1-minute limit reached — stopping recording…';
                stopLiveRecord();
            }
        }, 1000);
    }

    function setVideoFields(data) {
        document.getElementById('video_file').value = ''; // never store locally
        document.getElementById('video_source').value = data.source || '';
        document.getElementById('video_pcloud_fileid').value = data.pcloud_fileid || '';
        document.getElementById('video_pcloud_link').value = data.pcloud_link || '';
        const clearBtn = document.getElementById('videoClearBtn');
        if (clearBtn) clearBtn.classList.toggle('d-none', !(data.pcloud_link || data.pcloud_fileid));
    }

    function clearVideo() {
        if (videoPreviewUrl) {
            URL.revokeObjectURL(videoPreviewUrl);
            videoPreviewUrl = '';
        }
        setVideoFields({});
        const preview = document.getElementById('videoPreview');
        if (preview) {
            preview.removeAttribute('src');
            preview.srcObject = null;
            preview.load();
            preview.muted = true;
            preview.controls = false;
        }
        document.getElementById('videoStatus').textContent = 'No video yet — upload a file or start a live recording.';
        document.getElementById('videoProgressWrap').classList.add('d-none');
    }

    function uploadVideoBlob(blob, source, filename) {
        return new Promise((resolve, reject) => {
            uploadsInFlight++;
            setSubmitEnabled();
            if (videoPreviewUrl) {
                URL.revokeObjectURL(videoPreviewUrl);
            }
            videoPreviewUrl = URL.createObjectURL(blob);
            const preview = document.getElementById('videoPreview');
            preview.srcObject = null;
            preview.src = videoPreviewUrl;
            preview.muted = false;
            preview.controls = true;

            const fd = new FormData();
            fd.append('file', blob, filename || ('intro-' + source + '.webm'));
            fd.append('source', source);
            const xhr = new XMLHttpRequest();
            const wrap = document.getElementById('videoProgressWrap');
            const bar = document.getElementById('videoProgressBar');
            const status = document.getElementById('videoStatus');
            wrap.classList.remove('d-none');
            bar.style.width = '0%';
            status.textContent = 'Uploading video to pCloud (not saved on server)…';
            xhr.open('POST', 'fm_upload_video.php');
            xhr.upload.onprogress = e => {
                if (e.lengthComputable) {
                    bar.style.width = Math.round((e.loaded / e.total) * 100) + '%';
                }
            };
            xhr.onload = () => {
                uploadsInFlight = Math.max(0, uploadsInFlight - 1);
                setSubmitEnabled();
                let data;
                try { data = JSON.parse(xhr.responseText || '{}'); }
                catch (err) { reject(new Error('Video upload failed')); return; }
                if (!data.success) {
                    reject(new Error(data.message || 'Video upload failed'));
                    return;
                }
                setVideoFields(data);
                status.textContent = 'Video uploaded to pCloud only — not stored on this server.';
                bar.style.width = '100%';
                resolve(data);
            };
            xhr.onerror = () => {
                uploadsInFlight = Math.max(0, uploadsInFlight - 1);
                setSubmitEnabled();
                reject(new Error('Network error during video upload'));
            };
            xhr.send(fd);
        });
    }

    async function startLiveRecord() {
        try {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') return;
            mediaStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
            const preview = document.getElementById('videoPreview');
            preview.srcObject = mediaStream;
            preview.muted = true;
            preview.controls = false;
            await preview.play();
            recordedChunks = [];
            const mime = MediaRecorder.isTypeSupported('video/webm;codecs=vp9,opus')
                ? 'video/webm;codecs=vp9,opus'
                : (MediaRecorder.isTypeSupported('video/webm') ? 'video/webm' : '');
            mediaRecorder = mime ? new MediaRecorder(mediaStream, { mimeType: mime }) : new MediaRecorder(mediaStream);
            mediaRecorder.ondataavailable = e => { if (e.data && e.data.size > 0) recordedChunks.push(e.data); };
            mediaRecorder.onstop = async () => {
                stopRecordTimer();
                if (mediaStream) {
                    mediaStream.getTracks().forEach(t => t.stop());
                    mediaStream = null;
                }
                document.getElementById('videoStopBtn').classList.add('d-none');
                document.getElementById('videoRecordBtn').classList.remove('d-none');
                const blob = new Blob(recordedChunks, { type: mediaRecorder.mimeType || 'video/webm' });
                try {
                    await uploadVideoBlob(blob, 'record', 'intro-record.webm');
                } catch (err) {
                    document.getElementById('videoStatus').textContent = err.message || 'Recording upload failed';
                    showErrors([err.message || 'Recording upload failed']);
                }
            };
            mediaRecorder.start(1000);
            startRecordTimer();
            document.getElementById('videoRecordBtn').classList.add('d-none');
            document.getElementById('videoStopBtn').classList.remove('d-none');
            document.getElementById('videoStatus').textContent = 'Recording… follow the 7 points. Auto-stops at 3:00.';
        } catch (err) {
            stopRecordTimer();
            showErrors(['Camera/microphone permission is required to record live.']);
            document.getElementById('videoStatus').textContent = 'Could not access camera/microphone.';
        }
    }

    function stopLiveRecord() {
        stopRecordTimer();
        if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stop();
        }
    }

    document.getElementById('videoUploadBtn')?.addEventListener('click', () => document.getElementById('videoFileInput').click());
    document.getElementById('videoRecordBtn')?.addEventListener('click', startLiveRecord);
    document.getElementById('videoStopBtn')?.addEventListener('click', stopLiveRecord);
    document.getElementById('videoClearBtn')?.addEventListener('click', clearVideo);
    document.getElementById('videoFileInput')?.addEventListener('change', async function () {
        const file = this.files && this.files[0];
        this.value = '';
        if (!file) return;
        try {
            await uploadVideoBlob(file, 'upload', file.name);
        } catch (err) {
            showErrors([err.message || 'Video upload failed']);
            document.getElementById('videoStatus').textContent = err.message || 'Video upload failed';
        }
    });

    const fieldLabels = {
        full_name: 'Full Name',
        age: 'Age',
        nationality: 'Nationality',
        country_of_residence: 'Current Country of Residence',
        profession: 'Current Profession / Occupation',
        years_experience: 'Years of Professional Experience',
        email: 'Email',
        phone: 'Phone',
        highest_degree: 'Highest Degree Obtained',
        field_of_study: 'Field of Study',
        university_name: 'University / College Name',
        country_of_study: 'Country of Study',
        graduation_year: 'Graduation Year',
        french_level: 'French Level',
        french_professional: 'Can you work professionally in French?',
        english_level: 'English Level',
        english_professional: 'Can you work professionally in English?',
        has_wes: 'Do you have WES?'
    };

    function showErrors(items) {
        const box = document.getElementById('errorBox');
        const list = document.getElementById('errorList');
        list.innerHTML = '';
        items.forEach(t => {
            const li = document.createElement('li');
            li.textContent = t;
            list.appendChild(li);
        });
        box.classList.toggle('d-none', items.length === 0);
        if (items.length) box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function syncAcademicHidden() {
        const paths = academicUploads.map(u => u.path);
        document.getElementById('academic_docs_path').value = paths.length ? JSON.stringify(paths) : '';
    }

    function renderAcademicList() {
        const el = document.getElementById('academic-preview');
        if (!academicUploads.length) {
            el.innerHTML = '';
            return;
        }
        el.innerHTML = academicUploads.map((u, i) =>
            `<div class="upload-item">
                <span><i class="fas fa-check text-success me-1"></i>${escapeHtml(u.name)}</span>
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove" data-idx="${i}">Remove</button>
            </div>`
        ).join('');
        el.querySelectorAll('.btn-remove').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                academicUploads.splice(parseInt(btn.dataset.idx, 10), 1);
                syncAcademicHidden();
                renderAcademicList();
            });
        });
    }

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function setSubmitEnabled() {
        const btn = document.getElementById('submitBtn');
        btn.disabled = uploadsInFlight > 0;
        if (uploadsInFlight > 0) {
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Uploading files…';
        } else if (!btn.dataset.submitting) {
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Submit Application';
        }
    }

    function progressBarHtml(pct) {
        return `<div class="progress upload-progress"><div class="progress-bar" style="width:${pct}%"></div></div>`;
    }

    function uploadFile(file, field, onProgress) {
        return new Promise((resolve, reject) => {
            uploadsInFlight++;
            setSubmitEnabled();
            const fd = new FormData();
            fd.append('file', file);
            fd.append('field', field);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'fm_upload.php');
            xhr.upload.onprogress = e => {
                if (e.lengthComputable && onProgress) {
                    onProgress(Math.round((e.loaded / e.total) * 100));
                }
            };
            xhr.onload = () => {
                uploadsInFlight = Math.max(0, uploadsInFlight - 1);
                setSubmitEnabled();
                let data;
                try {
                    data = JSON.parse(xhr.responseText || '{}');
                } catch (err) {
                    reject(new Error('Upload failed'));
                    return;
                }
                if (!data.success) {
                    reject(new Error(data.message || 'Upload failed'));
                    return;
                }
                resolve({ path: data.file_path, name: file.name });
            };
            xhr.onerror = () => {
                uploadsInFlight = Math.max(0, uploadsInFlight - 1);
                setSubmitEnabled();
                reject(new Error('Network error during upload'));
            };
            xhr.send(fd);
        });
    }

    document.querySelectorAll('.upload-zone').forEach(zone => {
        const field = zone.dataset.field;
        const input = document.getElementById(field);
        const isMulti = zone.dataset.multiple === '1';

        zone.addEventListener('click', e => {
            if (e.target.closest('.btn-remove')) return;
            input.click();
        });
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
        zone.addEventListener('drop', e => {
            e.preventDefault(); zone.classList.remove('dragover');
            const files = [...(e.dataTransfer.files || [])];
            if (!files.length) return;
            handleFiles(files, field, isMulti, zone);
        });

        input.addEventListener('change', () => {
            const files = [...(input.files || [])];
            input.value = '';
            if (!files.length) return;
            handleFiles(files, field, isMulti, zone);
        });
    });

    document.getElementById('academicAddMore')?.addEventListener('click', () => {
        document.getElementById('academic').click();
    });

    function handleFiles(files, field, isMulti, zone) {
        if (!files.length) return;

        if (isMulti) {
            const preview = zone.querySelector('.preview');
            preview.innerHTML = '<span class="text-primary"><i class="fas fa-spinner fa-spin"></i> Uploading…</span>' + progressBarHtml(0);
            const bar = () => preview.querySelector('.progress-bar');
            Promise.all(files.map(f => uploadFile(f, field, pct => { if (bar()) bar().style.width = pct + '%'; })
                .then(res => { academicUploads.push(res); })
            )).then(() => {
                syncAcademicHidden();
                renderAcademicList();
            }).catch(err => {
                preview.innerHTML = '<span class="text-danger">' + escapeHtml(err.message) + '</span>';
            });
            return;
        }

        const f = files[0];
        const preview = zone.querySelector('.preview');
        preview.innerHTML = '<span class="text-primary"><i class="fas fa-spinner fa-spin"></i> Uploading…</span>' + progressBarHtml(0);
        const bar = () => preview.querySelector('.progress-bar');
        uploadFile(f, field, pct => { if (bar()) bar().style.width = pct + '%'; })
            .then(res => {
                uploads[field] = res.path;
                document.getElementById(field + '_path').value = res.path;
                preview.innerHTML = '<i class="fas fa-check text-success"></i> ' + escapeHtml(res.name);
            }).catch(err => {
                preview.innerHTML = '<span class="text-danger">' + escapeHtml(err.message) + '</span>';
            });
    }

    function hasAtLeastOneAttachment() {
        const singlePaths = ['cv_path', 'french_cert_path', 'english_cert_path'];
        for (const id of singlePaths) {
            const el = document.getElementById(id);
            if (el && String(el.value || '').trim() !== '') {
                return true;
            }
        }
        if (academicUploads.length > 0) {
            return true;
        }
        const academicHidden = document.getElementById('academic_docs_path');
        return !!(academicHidden && String(academicHidden.value || '').trim() !== '');
    }

    function collectMinimumRequired() {
        const missing = [];
        const fullName = document.getElementById('full_name');
        if (!fullName || !String(fullName.value || '').trim()) {
            missing.push('Full Name');
        }
        const emailEl = document.getElementById('email');
        if (!emailEl || !String(emailEl.value || '').trim()) {
            missing.push('Email');
        } else if (!emailEl.validity.valid) {
            missing.push('Valid Email');
        }
        const dobEl = document.getElementById('date_of_birth');
        if (!dobEl || !String(dobEl.value || '').trim()) {
            missing.push('Date of Birth');
        }
        const passportEl = document.getElementById('passport_number');
        if (!passportEl || !String(passportEl.value || '').trim()) {
            missing.push('Passport Number');
        }
        const nationalityEl = document.getElementById('nationality');
        if (!nationalityEl || !String(nationalityEl.value || '').trim()) {
            missing.push('Nationality');
        }
        const addressEl = document.getElementById('address');
        if (!addressEl || !String(addressEl.value || '').trim()) {
            missing.push('Full Address');
        }
        const jobOfferEl = form.querySelector('input[name="job_offer"]:checked');
        if (!jobOfferEl) {
            missing.push('Preferred Job Offer');
        }
        if (!hasAtLeastOneAttachment()) {
            missing.push('At least one attachment (CV, certificate, or academic document)');
        }
        return missing;
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        showErrors([]);

        const dial = phoneIti.getSelectedCountryData();
        document.getElementById('phone_area_code').value = dial.dialCode || '';
        let digits = (phoneIti.getNumber() || document.getElementById('phone').value || '').replace(/\D/g, '');
        const code = (dial.dialCode || '').replace(/\D/g, '');
        if (code && digits.startsWith(code)) digits = digits.slice(code.length);
        document.getElementById('phone_number').value = digits;

        syncAcademicHidden();

        if (uploadsInFlight > 0) {
            showErrors(['Please wait — files are still uploading.']);
            return;
        }

        const missing = collectMinimumRequired();
        if (missing.length) {
            showErrors(missing);
            return;
        }

        const btn = document.getElementById('submitBtn');
        const progressWrap = document.getElementById('submitProgress');
        const progressBar = document.getElementById('submitProgressBar');
        const progressPct = document.getElementById('submitProgressPct');
        const progressLabel = document.getElementById('submitProgressLabel');

        btn.disabled = true;
        btn.dataset.submitting = '1';
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Submitting…';
        progressWrap.classList.remove('d-none');
        progressLabel.textContent = 'Submitting application…';

        let pct = 5;
        const tick = setInterval(() => {
            if (pct < 90) {
                pct += Math.random() * 8;
                pct = Math.min(90, Math.round(pct));
                progressBar.style.width = pct + '%';
                progressPct.textContent = pct + '%';
            }
        }, 350);

        const finishProgress = (ok) => {
            clearInterval(tick);
            progressBar.style.width = ok ? '100%' : '0%';
            progressPct.textContent = ok ? '100%' : '0%';
            if (!ok) progressWrap.classList.add('d-none');
        };

        const fd = new FormData(form);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'save_francophonie_mobility_request.php');
        xhr.onload = () => {
            let data;
            const raw = xhr.responseText || '';
            try {
                data = JSON.parse(raw);
            } catch (err) {
                finishProgress(false);
                const snippet = raw.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200);
                showErrors([snippet || ('Server error (HTTP ' + xhr.status + '). Please try again.')]);
                btn.disabled = false;
                delete btn.dataset.submitting;
                btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Submit Application';
                return;
            }
            if (!data.success) {
                finishProgress(false);
                const items = Array.isArray(data.missing) && data.missing.length
                    ? data.missing
                    : [data.message || 'Submission failed'];
                showErrors(items);
                btn.disabled = false;
                delete btn.dataset.submitting;
                btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Submit Application';
                return;
            }
            finishProgress(true);
            progressLabel.textContent = 'Done!';
            document.getElementById('formPanel').style.display = 'none';
            document.getElementById('successPanel').style.display = 'block';
            document.getElementById('successRef').textContent = data.reference_id;

            if (data.request_id && data.email_token && navigator.sendBeacon) {
                const emailBody = new URLSearchParams({
                    application_id: String(data.request_id),
                    token: data.email_token
                });
                navigator.sendBeacon('fm_background_email.php', emailBody);
            }
        };
        xhr.onerror = () => {
            finishProgress(false);
            showErrors(['Network error. Check your connection and try again.']);
            btn.disabled = false;
            delete btn.dataset.submitting;
            btn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Submit Application';
        };
        xhr.send(fd);
    });
})();
</script>
</body>
</html>
