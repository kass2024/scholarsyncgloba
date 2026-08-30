<?php
declare(strict_types=1);

/**
 * Wrap currency amounts (USD, CAD, EUR, €) in a highlight span for contracts.
 */
function highlightContractPrices(?string $text): string
{
    $text = (string) $text;
    if ($text === '') {
        return '';
    }

    $num = '(?:\d{1,3}(?:,\d{3})*|\d+)(?:\.\d+)?';

    $pattern = '/(?:
        (?:USD|CAD|EUR)\s+
        ' . $num . '
        (?:
            \s*(?:–|-|to)\s*
            (?:(?:USD|CAD|EUR)\s+)?
            ' . $num . '
        )?
        |
        €\s*
        ' . $num . '
        (?:
            \s*(?:–|-)\s*
            €?\s*
            ' . $num . '
        )?
    )/ix';

    return (string) preg_replace_callback(
        $pattern,
        static fn (array $m): string =>
            '<span class="fee-price">' . htmlspecialchars($m[0], ENT_QUOTES, 'UTF-8') . '</span>',
        $text
    );
}
