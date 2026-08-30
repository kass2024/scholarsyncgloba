<?php
/**
 * francophonie_mobility_application_details.php — Admin detail + document viewer.
 */
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers/francophonie_mobility_schema.php';
fm_ensure_schema($conn);
require_once __DIR__ . '/helpers/francophonie_mobility_notify.php';
require_once __DIR__ . '/helpers/francophonie_mobility_files.php';
require_once __DIR__ . '/helpers/fm_public_share.php';
require_once __DIR__ . '/helpers/secure_file.php';

if (empty($_SESSION['id'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied</div>';
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    echo '<div class="alert alert-danger">Invalid ID</div>';
    exit;
}

$st = $conn->prepare('SELECT * FROM francophonie_mobility_applications WHERE id = ? LIMIT 1');
$st->bind_param('i', $id);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
    echo '<div class="alert alert-danger">Not found</div>';
    exit;
}

function fm_doc_link(string $relPath, string $label): string
{
    return pcvc_secure_file_links_html($relPath, $label);
}

$name = htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name']), ENT_QUOTES, 'UTF-8');
$ref = htmlspecialchars($row['reference_id'], ENT_QUOTES, 'UTF-8');
$status = htmlspecialchars(ucwords(str_replace('_', ' ', $row['status'])), ENT_QUOTES, 'UTF-8');

$frenchCerts = [];
if ($row['french_tef']) $frenchCerts[] = 'TEF';
if ($row['french_tcf']) $frenchCerts[] = 'TCF';
$englishCerts = [];
if ($row['english_toefl']) $englishCerts[] = 'TOEFL';
if ($row['english_ielts']) $englishCerts[] = 'IELTS';

$videoToken = trim((string) ($row['video_public_token'] ?? ''));
$videoPcloud = trim((string) ($row['video_pcloud_link'] ?? ''));
$videoLocal = trim((string) ($row['video_file'] ?? ''));
$hasVideo = $videoLocal !== '' || $videoPcloud !== '';
$inviteToken = trim((string) ($row['video_invite_token'] ?? ''));
$inviteUsed = !empty($row['video_invite_used_at']);

// Ensure dual-token secured share credentials exist for copy / view-details.
[$shareToken, $shareSecret] = fm_ensure_public_share_tokens($conn, $row);
$publicDetailsUrl = ($shareToken !== '' && $shareSecret !== '')
    ? fm_public_details_url($shareToken, $shareSecret)
    : '';
$secureVideoUrl = ($hasVideo && $shareToken !== '' && $shareSecret !== '')
    ? fm_public_video_url($shareToken, $shareSecret)
    : '';

$inviteUrl = ($inviteToken !== '' && !$inviteUsed && !$hasVideo)
    ? fm_public_base_url() . '/fm-video-invite.php?t=' . rawurlencode($inviteToken)
    : '';
$ownerPlain = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
$phoneDigits = preg_replace('/\D+/', '', (string) (($row['phone_area_code'] ?? '') . ($row['phone_number'] ?? ''))) ?: '';
$inviteWaText = $inviteUrl !== ''
    ? "Hello {$ownerPlain},\n\n"
        . "Please upload or record your introduction video for Canada Francophonie Mobility.\n"
        . "Reference: " . ($row['reference_id'] ?? '') . "\n"
        . "One-time link (upload/record only): {$inviteUrl}\n\n"
        . "This link can be used once. Thank you."
    : '';
$inviteWaUrl = $inviteWaText !== ''
    ? ($phoneDigits !== ''
        ? 'https://wa.me/' . $phoneDigits . '?text=' . rawurlencode($inviteWaText)
        : 'https://wa.me/?text=' . rawurlencode($inviteWaText))
    : '';
$copyBundle = $publicDetailsUrl !== ''
    ? fm_public_copy_bundle($row, $publicDetailsUrl)
    : '';
$appId = (int) $row['id'];
?>
<div class="row g-3">
    <div class="col-lg-8">
        <?= fm_build_form_summary_html($row) ?>
        <?php if (!empty($row['admin_notes'])): ?>
        <div class="alert alert-secondary mt-3">
            <strong>Admin notes:</strong><br><?= nl2br(htmlspecialchars($row['admin_notes'], ENT_QUOTES, 'UTF-8')) ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($row['approval_package_sent_at'])): ?>
        <p class="small text-success"><i class="fas fa-envelope-circle-check"></i> Approval package emailed on <?= htmlspecialchars($row['approval_package_sent_at']) ?></p>
        <?php endif; ?>

        <?php if ($publicDetailsUrl !== ''): ?>
        <div class="card mt-3 border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-2"><i class="fas fa-lock me-2 text-primary"></i>Secured view details</h6>
                <p class="small text-muted mb-3">
                    Full application + attachments<?= $hasVideo ? ' + video' : '' ?>.
                    Link uses two keys — pCloud download URL is never shared.
                </p>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars($publicDetailsUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        <i class="fas fa-external-link-alt me-1"></i> Open view details
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-primary"
                            data-copy="<?= htmlspecialchars($copyBundle, ENT_QUOTES, 'UTF-8') ?>"
                            onclick="copyFmFromBtn(this)">
                        <i class="fas fa-copy me-1"></i> Copy view details + owner
                    </button>
                    <?php if ($secureVideoUrl !== ''): ?>
                    <a class="btn btn-sm btn-outline-danger" href="<?= htmlspecialchars($secureVideoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        <i class="fas fa-play me-1"></i> Open video
                    </a>
                    <?php endif; ?>
                </div>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="fmPublicVideoUrl" readonly value="<?= htmlspecialchars($publicDetailsUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="button" class="btn btn-outline-secondary" onclick="copyFmInput('fmPublicVideoUrl', this)">Copy URL</button>
                </div>
                <?php if ($hasVideo): ?>
                <p class="small text-muted mt-2 mb-0">
                    Video source: <strong><?= htmlspecialchars(ucfirst((string) ($row['video_source'] ?: 'upload')), ENT_QUOTES, 'UTF-8') ?></strong>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$hasVideo): ?>
        <div class="card mt-3 border-0 shadow-sm">
            <div class="card-body">
                <h6 class="card-title mb-2"><i class="fas fa-video me-2 text-muted"></i>No introduction video yet</h6>
                <p class="small text-muted mb-3">Create a one-time link so this candidate can only upload or record a video (not the full form).</p>
                <?php if ($inviteUrl !== ''): ?>
                <div class="alert alert-info py-2 small mb-3">
                    Invite ready
                    <?php if (!empty($row['video_invite_opened_at'])): ?>
                    · opened <?= htmlspecialchars((string) $row['video_invite_opened_at'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                    <?php if (!empty($row['video_invite_created_at'])): ?>
                    · created <?= htmlspecialchars((string) $row['video_invite_created_at'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control" id="fmInviteUrl" readonly value="<?= htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="button" class="btn btn-outline-secondary"
                            data-copy="<?= htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8') ?>"
                            onclick="copyFmFromBtn(this)">Copy link</button>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary"
                            data-copy="<?= htmlspecialchars($inviteWaText, ENT_QUOTES, 'UTF-8') ?>"
                            onclick="copyFmFromBtn(this)">
                        <i class="fas fa-copy me-1"></i> Copy message + reference
                    </button>
                    <?php if ($inviteWaUrl !== ''): ?>
                    <a class="btn btn-sm btn-success" href="<?= htmlspecialchars($inviteWaUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                        <i class="fab fa-whatsapp me-1"></i> WhatsApp
                    </a>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="createVideoInvite(<?= $appId ?>, true)">
                        <i class="fas fa-sync me-1"></i> Regenerate link
                    </button>
                </div>
                <?php else: ?>
                <button type="button" class="btn btn-sm btn-primary" onclick="createVideoInvite(<?= $appId ?>)">
                    <i class="fas fa-link me-1"></i> Create video upload link
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Quick actions</h6>
                <p class="small text-muted mb-2">Status actions notify the candidate by <strong>email only</strong>.</p>
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-warning btn-sm" onclick="setStatus(<?= $appId ?>, 'under_review')">Mark Under Review</button>
                    <button type="button" class="btn btn-success btn-sm" onclick="setStatus(<?= $appId ?>, 'approved')">Approve &amp; Send Package</button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="setStatus(<?= $appId ?>, 'rejected')">Reject</button>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="resendEmail(<?= $appId ?>)">
                        <i class="fas fa-paper-plane"></i> Resend status email
                    </button>
                    <?php if (($row['status'] ?? '') === 'approved'): ?>
                    <button type="button" class="btn btn-outline-success btn-sm" onclick="resendPackage(<?= $appId ?>)">
                        <i class="fas fa-file-export"></i> Resend approval package
                    </button>
                    <?php endif; ?>
                    <?php if (!$hasVideo): ?>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="createVideoInvite(<?= $appId ?><?= $inviteUrl !== '' ? ', true' : '' ?>)">
                        <i class="fas fa-video"></i> <?= $inviteUrl !== '' ? 'Regenerate video link' : 'Send video upload link' ?>
                    </button>
                    <?php endif; ?>
                    <a href="admin-generate-fm-contract.php?application_id=<?= $appId ?>" target="_blank" class="btn btn-outline-dark btn-sm">
                        <i class="fas fa-file-signature"></i> Issue E-Sign Contract
                    </a>
                    <a href="mailto:<?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-envelope"></i> Open in mail client
                    </a>
                    <hr class="my-1">
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteApplication(<?= $appId ?>, <?= json_encode($row['reference_id']) ?>)">
                        <i class="fas fa-trash"></i> Delete full application
                    </button>
                </div>
                <hr>
                <p class="small mb-1"><strong>Reference:</strong> <code><?= $ref ?></code></p>
                <p class="small mb-1"><strong>Status:</strong> <?= $status ?></p>
                <p class="small mb-0"><strong>User ID:</strong> <code><?= htmlspecialchars($row['user_id']) ?></code></p>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-body">
                <h6 class="card-title">Documents</h6>
                <?= fm_doc_link($row['cv_file'] ?? '', 'CV') ?>
                <?= fm_doc_link($row['french_cert_file'] ?? '', 'French Certificate') ?>
                <?= fm_doc_link($row['english_cert_file'] ?? '', 'English Certificate') ?>
                <?php
                $academicList = fm_parse_stored_files((string) ($row['academic_docs_file'] ?? ''));
                if ($academicList === []) {
                    echo '<div class="text-muted small">Academic Documents: none uploaded</div>';
                } else {
                    foreach ($academicList as $i => $apath) {
                        $label = count($academicList) > 1 ? 'Academic Document ' . ($i + 1) : 'Academic Documents';
                        echo fm_doc_link($apath, $label);
                    }
                }
                ?>
                <?php if ($videoLocal !== '' || $videoPcloud !== ''): ?>
                <hr>
                <div class="small fw-semibold mb-1">Video</div>
                <?php if ($publicDetailsUrl !== ''): ?>
                <a href="<?= htmlspecialchars($publicDetailsUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-danger w-100 mb-1">
                    <i class="fas fa-id-card me-1"></i> View details
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
