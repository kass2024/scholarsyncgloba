<?php
// Fix the missing selected_package_code hidden input
$file = 'student-contract.php';
$content = file_get_contents($file);

// Find the line with </div> followed by </div> and empty lines, then the comment
$pattern = '/<\/div>\n<\/div>\n\n\n<!-- ============================\n     ARTICLE 8 – PAYMENT OF SERVICE FEES/';
$replacement = '</div>' . "\n" . '<input type="hidden" id="selected_package_code" value="">' . "\n" . '</div>' . "\n\n" . '<!-- ============================' . "\n     ARTICLE 8 – PAYMENT OF SERVICE FEES/';

$content = preg_replace($pattern, $replacement, $content);

file_put_contents($file, $content);
echo "Fixed selected_package_code hidden input field";
?>
