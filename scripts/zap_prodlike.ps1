param(
    [switch]$Prep,
    [switch]$Serve,
    [switch]$Restore
)

if (-not $Prep -and -not $Restore) {
    $Prep = $true
}

$repoRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Push-Location $repoRoot

$hotFile = Join-Path $repoRoot 'public/hot'
$backupFile = Join-Path $repoRoot 'public/hot.zap.bak'

if ($Prep) {
    if (Test-Path $hotFile) {
        if (-not (Test-Path $backupFile)) {
            Move-Item -Path $hotFile -Destination $backupFile
        } else {
            Remove-Item -Path $hotFile -Force
        }
    }

    Write-Host "Building Vite assets (npm run build)..." -ForegroundColor Cyan
    npm run build
    if ($LASTEXITCODE -ne 0) {
        throw "npm run build failed (exit code: $LASTEXITCODE)."
    }

    Write-Host "" 
    Write-Host "Next: start Laravel in production-like mode:" -ForegroundColor Cyan
    Write-Host "  `$env:APP_ENV='production'; `$env:APP_DEBUG='false'; php -S 127.0.0.1:8000 -t `"$repoRoot`" `"$repoRoot\server.php`"" -ForegroundColor Gray
    Write-Host "Then run ZAP against http://127.0.0.1:8000" -ForegroundColor Gray
}

if ($Serve) {
    Write-Host "Starting server on http://127.0.0.1:8000 ..." -ForegroundColor Cyan
    Write-Host "(Uses server.php router so static assets also get security headers.)" -ForegroundColor Gray

    # Ensure we don't accidentally expose the Vite dev server during scans.
    if (Test-Path $hotFile) {
        if (-not (Test-Path $backupFile)) {
            Move-Item -Path $hotFile -Destination $backupFile
        } else {
            Remove-Item -Path $hotFile -Force
        }
    }

    $env:APP_ENV = 'production'
    $env:APP_DEBUG = 'false'

    php -S 127.0.0.1:8000 -t "$repoRoot" (Join-Path $repoRoot 'server.php')
}

if ($Restore) {
    if (Test-Path $backupFile) {
        if (Test-Path $hotFile) {
            Remove-Item -Path $hotFile -Force
        }
        Move-Item -Path $backupFile -Destination $hotFile
        Write-Host "Restored public/hot from public/hot.zap.bak" -ForegroundColor Cyan
    } else {
        Write-Host "No backup found at public/hot.zap.bak (nothing to restore)." -ForegroundColor Yellow
    }
}

Pop-Location
