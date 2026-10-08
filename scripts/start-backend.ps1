param(
    [ValidateRange(1, 65535)]
    [int]$Port = 8000
)

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$localPhpIni = Join-Path $projectRoot '.local-tools\php.ini'
$phpCommand = Get-Command php -ErrorAction SilentlyContinue

if ($phpCommand) {
    $phpExe = $phpCommand.Source
} else {
    $phpExe = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
}

if (-not (Test-Path -LiteralPath $phpExe)) {
    throw 'PHP 8.4 not found. Install PHP or reopen PowerShell and retry.'
}

if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'vendor\autoload.php'))) {
    throw 'Laravel dependencies missing. Run composer install in this project first.'
}

if (-not (Test-Path -LiteralPath (Join-Path $projectRoot '.env'))) {
    throw '.env missing. Copy .env.example and configure the local database.'
}

if (Test-Path -LiteralPath $localPhpIni) {
    $env:PHPRC = $localPhpIni
}

$env:Path = "$(Split-Path -Path $phpExe -Parent);$env:Path"
Set-Location -LiteralPath $projectRoot
Write-Host "Laravel API: http://127.0.0.1:$Port/"
Write-Host "Reading API: http://127.0.0.1:$Port/api/v1/reading-items"
Write-Host 'Press Ctrl+C to stop the server.'
& $phpExe artisan serve --host=127.0.0.1 --port=$Port
