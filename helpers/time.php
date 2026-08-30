<?php
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    if ($time < 60) return "just now";
    if ($time < 3600) return floor($time / 60) . "m ago";
    if ($time < 86400) return floor($time / 3600) . "h ago";
    return floor($time / 86400) . "d ago";
}
