param(
    [switch]$SkipInstall
)

$ErrorActionPreference = "Stop"

$repositoryRoot = Split-Path -Parent $PSScriptRoot
$frontendRoot = Join-Path $repositoryRoot "frontend"
$distRoot = Join-Path $frontendRoot "dist"
$artifactRoot = Join-Path $repositoryRoot "deployment/artifacts/frontend"
$npmCache = Join-Path $repositoryRoot "tmp/npm-cache"
$previousNpmCache = $env:npm_config_cache

function Assert-FrontendArtifact([string] $root) {
    if (-not (Test-Path -LiteralPath (Join-Path $root "index.html"))) {
        throw "The frontend artifact does not contain index.html: $root"
    }

    $assetsRoot = Join-Path $root "assets"
    $javascriptFiles = @(Get-ChildItem -LiteralPath $assetsRoot -Filter "*.js" -File)
    if ($javascriptFiles.Count -eq 0) {
        throw "The frontend artifact does not contain a JavaScript bundle: $root"
    }

    foreach ($javascriptFile in $javascriptFiles) {
        $javascript = [System.IO.File]::ReadAllText($javascriptFile.FullName)
        if ($javascript -match '[A-Za-z]:[/\\][^"''``]*[/\\]api') {
            throw "The frontend bundle contains a Windows filesystem API URL: $($javascriptFile.Name)"
        }
    }
}

New-Item -ItemType Directory -Force -Path $npmCache | Out-Null

Push-Location $frontendRoot
try {
    $env:npm_config_cache = $npmCache
    if (-not $SkipInstall) {
        & npm.cmd ci --no-audit --no-fund
        if ($LASTEXITCODE -ne 0) {
            throw "npm ci failed with exit code $LASTEXITCODE."
        }
    }

    $env:VITE_API_URL = "/api"
    & npm.cmd run build
    if ($LASTEXITCODE -ne 0) {
        throw "npm run build failed with exit code $LASTEXITCODE."
    }
}
finally {
    Remove-Item Env:VITE_API_URL -ErrorAction SilentlyContinue
    if ($null -eq $previousNpmCache) {
        Remove-Item Env:npm_config_cache -ErrorAction SilentlyContinue
    }
    else {
        $env:npm_config_cache = $previousNpmCache
    }
    Pop-Location
}

# Validate dist before replacing the last known-good committed artifact.
Assert-FrontendArtifact $distRoot

$expectedArtifactRoot = Join-Path $repositoryRoot "deployment/artifacts/frontend"
if ([System.IO.Path]::GetFullPath($artifactRoot) -ne [System.IO.Path]::GetFullPath($expectedArtifactRoot)) {
    throw "Refusing to replace an unexpected artifact path: $artifactRoot"
}

if (Test-Path -LiteralPath $artifactRoot) {
    Remove-Item -Recurse -Force -LiteralPath $artifactRoot
}

New-Item -ItemType Directory -Force -Path $artifactRoot | Out-Null
Copy-Item -Path (Join-Path $distRoot "*") -Destination $artifactRoot -Recurse -Force
Assert-FrontendArtifact $artifactRoot

Write-Host "cPanel frontend artifact is ready: $artifactRoot"
