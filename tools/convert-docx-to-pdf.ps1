param(
    [Parameter(Mandatory = $true)][string]$DocxPath,
    [Parameter(Mandatory = $true)][string]$PdfPath
)

$ErrorActionPreference = 'Stop'
$docxPath = (Resolve-Path -LiteralPath $DocxPath).Path
$pdfPath = [System.IO.Path]::GetFullPath($PdfPath)
$pdfDir = Split-Path -Parent $pdfPath
if (-not (Test-Path -LiteralPath $pdfDir)) {
    New-Item -ItemType Directory -Path $pdfDir -Force | Out-Null
}
if (Test-Path -LiteralPath $pdfPath) {
    Remove-Item -LiteralPath $pdfPath -Force
}

$word = $null
$doc = $null
try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    $doc = $word.Documents.Open($docxPath, $false, $true)
    # wdFormatPDF = 17
    $doc.SaveAs2([ref]$pdfPath, [ref]17)
    $doc.Close([ref]$false)
} finally {
    if ($doc -ne $null) {
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($doc)
    }
    if ($word -ne $null) {
        $word.Quit()
        [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($word)
    }
    [GC]::Collect()
    [GC]::WaitForPendingFinalizers()
}

if (-not (Test-Path -LiteralPath $pdfPath)) {
    Write-Error 'Word did not create the PDF file.'
}
Write-Output $pdfPath
