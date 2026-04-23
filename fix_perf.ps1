$rootPath = "c:\xampp\htdocs\udruwisdom"
$phpFiles = Get-ChildItem -Path $rootPath -Filter "*.php" -File
$updatedFiles = @()

foreach ($file in $phpFiles) {
    $content = Get-Content $file.FullName -Raw -Encoding UTF8
    $original = $content
    $changed = $false

    # ============================================================
    # Fix 1: Lucide — pin version + add defer
    # ============================================================
    if ($content -match 'unpkg\.com/lucide@latest"') {
        $content = $content -replace 'unpkg\.com/lucide@latest"', 'unpkg.com/lucide@0.475.0" defer'
        $changed = $true
    }

    # ============================================================
    # Fix 2a: Reduce Inter font weights (400;500;600;700;800 → 400;600;700;800)
    # ============================================================
    if ($content -match 'Inter:wght@400;500;600;700;800') {
        $content = $content -replace 'Inter:wght@400;500;600;700;800', 'Inter:wght@400;600;700;800'
        $changed = $true
    }

    # ============================================================
    # Fix 2b: Reduce Sarabun font weights (various patterns)
    # ============================================================
    if ($content -match 'Sarabun:wght@300;400;500;600;700') {
        $content = $content -replace 'Sarabun:wght@300;400;500;600;700', 'Sarabun:wght@400;500;700'
        $changed = $true
    }
    if ($content -match 'Sarabun:wght@400;500;600;700') {
        $content = $content -replace 'Sarabun:wght@400;500;600;700', 'Sarabun:wght@400;500;700'
        $changed = $true
    }

    # ============================================================
    # Fix 2c: Add preconnect before Google Fonts link (if not already done)
    # ============================================================
    if ($content -notmatch 'preconnect.*fonts\.googleapis\.com' -and $content -match 'fonts\.googleapis\.com') {
        $content = $content -replace '(\n[ \t]+)<link(\s+\r?\n[ \t]+href="https://fonts\.googleapis\.com)', '$1<link rel="preconnect" href="https://fonts.googleapis.com">$1<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>$1<link$2'
        $changed = $true
    }

    if ($changed) {
        Set-Content $file.FullName -Value $content -Encoding UTF8 -NoNewline
        $updatedFiles += $file.Name
    }
}

Write-Host ""
Write-Host "===== เสร็จสิ้น =====" -ForegroundColor Green
Write-Host "แก้ไขทั้งหมด $($updatedFiles.Count) ไฟล์:" -ForegroundColor Cyan
foreach ($f in $updatedFiles) { Write-Host "  ✓ $f" -ForegroundColor White }
