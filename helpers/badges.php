<?php
function statusBadge($label, $value) {
    if (!$value) return '';
    return "<span class='px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded'>$label</span>";
}

function unreadDot($is_read) {
    return !$is_read
        ? "<span class='w-2 h-2 bg-blue-600 rounded-full inline-block'></span>"
        : "";
}
