[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$releaseDir = Join-Path $root 'release'
$stage = Join-Path ([System.IO.Path]::GetTempPath()) ('brother-tours-release-' + [guid]::NewGuid().ToString('N'))

function Remove-DevelopmentFiles {
    param([Parameter(Mandatory)][string]$Path)

    Get-ChildItem -LiteralPath $Path -Recurse -Force -File |
        Where-Object { $_.Name -like '_preview*.html' -or $_.Extension -eq '.map' -or $_.Name -eq '.DS_Store' } |
        Remove-Item -Force

    Get-ChildItem -LiteralPath $Path -Recurse -Force -Directory |
        Where-Object { $_.Name -in @('tests', 'node_modules', 'vendor') } |
        Sort-Object FullName -Descending |
        Remove-Item -Recurse -Force
}

function Copy-TreeContents {
    param(
        [Parameter(Mandatory)][string]$Source,
        [Parameter(Mandatory)][string]$Destination
    )

    New-Item -ItemType Directory -Path $Destination -Force | Out-Null
    Get-ChildItem -LiteralPath $Source -Force | Copy-Item -Destination $Destination -Recurse -Force
}

function New-ComponentPackage {
    param(
        [Parameter(Mandatory)][string]$SourceRelative,
        [Parameter(Mandatory)][string]$PackageName
    )

    $componentRoot = Join-Path $stage $PackageName
    Copy-TreeContents (Join-Path $root $SourceRelative) $componentRoot
    Remove-DevelopmentFiles $componentRoot
    Compress-Archive -LiteralPath $componentRoot -DestinationPath (Join-Path $releaseDir "$PackageName-3.0.0.zip") -CompressionLevel Optimal -Force
}

try {
    if (Test-Path -LiteralPath $releaseDir) {
        Remove-Item -LiteralPath $releaseDir -Recurse -Force
    }
    New-Item -ItemType Directory -Path $releaseDir, $stage -Force | Out-Null

    New-ComponentPackage 'themes/wpistic' 'wpistic'
    New-ComponentPackage 'themes/brother-tours' 'brother-tours'
    New-ComponentPackage 'plugins/formistic' 'formistic'
    New-ComponentPackage 'plugins/wpistic-tour-manager' 'wpistic-tour-manager'
    New-ComponentPackage 'plugins/brother-tours-content-studio' 'brother-tours-content-studio'
    New-ComponentPackage 'plugins/brother-tours-operations-api' 'brother-tours-operations-api'

    $suiteRoot = Join-Path $stage 'brother-tours-suite'
    New-Item -ItemType Directory -Path $suiteRoot -Force | Out-Null
    foreach ($mapping in @(
        @('themes/wpistic', 'themes/wpistic'),
        @('themes/brother-tours', 'themes/brother-tours'),
        @('plugins/formistic', 'plugins/formistic'),
        @('plugins/wpistic-tour-manager', 'plugins/wpistic-tour-manager'),
        @('plugins/brother-tours-content-studio', 'plugins/brother-tours-content-studio'),
        @('plugins/brother-tours-operations-api', 'plugins/brother-tours-operations-api'),
        @('docs', 'docs')
    )) {
        Copy-TreeContents (Join-Path $root $mapping[0]) (Join-Path $suiteRoot $mapping[1])
    }
    Remove-Item -LiteralPath (Join-Path $suiteRoot 'docs/implementation-plan.md'), (Join-Path $suiteRoot 'docs/source-inventory.md') -Force -ErrorAction SilentlyContinue
    Copy-Item -LiteralPath (Join-Path $root 'README.md'), (Join-Path $root 'CHANGELOG.md') -Destination $suiteRoot -Force
    Remove-DevelopmentFiles $suiteRoot
    Compress-Archive -LiteralPath $suiteRoot -DestinationPath (Join-Path $releaseDir 'brother-tours-suite-3.0.0.zip') -CompressionLevel Optimal -Force

    $checksumLines = Get-ChildItem -LiteralPath $releaseDir -Filter '*.zip' | Sort-Object Name | ForEach-Object {
        $hash = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant()
        "$hash  $($_.Name)"
    }
    Set-Content -LiteralPath (Join-Path $releaseDir 'checksums.sha256') -Value $checksumLines -Encoding ascii

    foreach ($line in Get-Content -LiteralPath (Join-Path $releaseDir 'checksums.sha256')) {
        if ($line -notmatch '^([0-9a-fA-F]{64})\s+(.+)$') { throw "Invalid checksum line: $line" }
        $actual = (Get-FileHash -LiteralPath (Join-Path $releaseDir $Matches[2]) -Algorithm SHA256).Hash
        if ($actual -ine $Matches[1]) { throw "Checksum mismatch: $($Matches[2])" }
    }

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    foreach ($zip in Get-ChildItem -LiteralPath $releaseDir -Filter '*.zip') {
        $rootName = $zip.BaseName -replace '-3\.0\.0$', ''
        $entries = [System.IO.Compression.ZipFile]::OpenRead($zip.FullName).Entries.FullName
        if ($entries.Count -eq 0 -or @($entries | Where-Object { -not $_.StartsWith("$rootName/") }).Count -gt 0) {
            throw "ZIP root check failed: $($zip.Name)"
        }
    }

    Write-Host "Release packages written to $releaseDir"
    Get-ChildItem -LiteralPath $releaseDir | Select-Object Name, Length
}
finally {
    if (Test-Path -LiteralPath $stage) {
        Remove-Item -LiteralPath $stage -Recurse -Force
    }
}
