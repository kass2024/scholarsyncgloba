<?php
/**
 * COOP/COEP headers required for Zoom gallery view + screen share (SharedArrayBuffer).
 * @see ScholarSync Learning frontend public/.htaccess
 */
declare(strict_types=1);

function fm_zoom_send_coop_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Embedder-Policy: credentialless');
    header('Permissions-Policy: camera=(self), microphone=(self)');
}
