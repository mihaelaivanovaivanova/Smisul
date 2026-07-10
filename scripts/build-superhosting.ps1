param(
    [switch]$SkipFrontendInstall,
    [switch]$SkipComposerInstall
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$release = Join-Path $root "release-superhosting"
$public = Join-Path $release "root"
$backend = Join-Path $release "backend"

if (-not $SkipFrontendInstall) {
    Push-Location (Join-Path $root "frontend")
    npm ci
    Pop-Location

}

if (-not $SkipComposerInstall) {
    Push-Location (Join-Path $root "backend")
    composer install --no-dev --optimize-autoloader --no-interaction
    Pop-Location
}

# A relative API URL keeps the archive independent from the final domain.
$env:VITE_API_URL = "/api"
Push-Location (Join-Path $root "frontend")
npm run build
Pop-Location

if (Test-Path $release) { Remove-Item -Recurse -Force -LiteralPath $release }
New-Item -ItemType Directory -Force -Path $public, $backend | Out-Null

Copy-Item (Join-Path $root "frontend/dist/*") $public -Recurse -Force
Copy-Item (Join-Path $root "deployment/public_html/.htaccess") $public -Force
Copy-Item (Join-Path $root "deployment/public_html/laravel.php") $public -Force
Copy-Item (Join-Path $root "deployment/public_html/install.php") $public -Force

$excluded = @("tests", ".env", ".gitignore", "phpunit.xml", "phpstan.neon", "README.md")
Get-ChildItem (Join-Path $root "backend") -Force | Where-Object { $excluded -notcontains $_.Name } | ForEach-Object {
    Copy-Item $_.FullName $backend -Recurse -Force
}
# Never ship local/test SQLite databases in a production package.
Get-ChildItem $backend -Recurse -Filter *.sqlite -File | ForEach-Object {
    Remove-Item -Force -LiteralPath $_.FullName
}
Copy-Item (Join-Path $root "deployment/backend.env.production.example") (Join-Path $backend ".env.example") -Force
Copy-Item (Join-Path $root "deployment/install-config.php") (Join-Path $backend "install-config.example.php") -Force
$icardImport = Join-Path $root "deployment/private/icard-import.php"
if (Test-Path -LiteralPath $icardImport) {
    $privateStorage = Join-Path $backend "storage/app/private"
    New-Item -ItemType Directory -Force -Path $privateStorage | Out-Null
    Copy-Item -LiteralPath $icardImport -Destination (Join-Path $privateStorage "icard-import.php") -Force
}

$zip = Join-Path $root "smisul-superhosting.zip"
if (Test-Path $zip) { Remove-Item -Force -LiteralPath $zip }
# bsdtar is substantially faster than Compress-Archive for Composer's many files
# and preserves dotfiles such as root/.htaccess.
& tar.exe -a -c -f $zip -C $release root backend
if ($LASTEXITCODE -ne 0) { throw "Could not create deployment ZIP." }
Write-Host "Ready: $zip"
