[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
Push-Location $projectRoot
try {
  & npm.cmd run lint
  if ($LASTEXITCODE -ne 0) { throw 'Frontend lint failed.' }
  & npm.cmd run build
  if ($LASTEXITCODE -ne 0) { throw 'Production build failed.' }
  & npm.cmd audit --omit=dev
  if ($LASTEXITCODE -ne 0) { throw 'Production dependency audit failed.' }

  Get-ChildItem api -Recurse -Filter '*.php' | ForEach-Object {
    & php -l $_.FullName | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "PHP syntax check failed: $($_.FullName)" }
  }
  & php tests\subfolder-routing.php
  if ($LASTEXITCODE -ne 0) { throw 'Arbitrary-subfolder routing checks failed.' }

  try {
    $env:EMC_APP_ENV = 'production'
    $env:EMC_DB_HOST = '127.0.0.1'
    $env:EMC_DB_NAME = 'emc_release_probe'
    $env:EMC_DB_USER = 'emc_release_user'
    $env:EMC_DB_PASS = 'release-probe-password'
    $env:EMC_ALLOWED_ORIGINS = 'https://emc.example.test'
    $env:EMC_APP_KEY = 'replace-with-a-unique-random-string-of-at-least-32-characters'
    $ErrorActionPreference = 'SilentlyContinue'
    & php tests\bootstrap-config.php 2>$null | Out-Null
    $appKeyProbeExit = $LASTEXITCODE

    $env:EMC_APP_KEY = 'release-probe-key-with-more-than-thirty-two-characters'
    $env:EMC_DB_NAME = 'hostinger_database_name'
    & php tests\bootstrap-config.php 2>$null | Out-Null
    $databaseProbeExit = $LASTEXITCODE

    $env:EMC_DB_NAME = 'emc_release_probe'
    $env:EMC_ALLOWED_ORIGINS = 'https://example.com'
    & php tests\bootstrap-config.php 2>$null | Out-Null
    $originProbeExit = $LASTEXITCODE
    $ErrorActionPreference = 'Stop'
    if ($appKeyProbeExit -eq 0) { throw 'Production accepted the shipped app-key placeholder.' }
    if ($databaseProbeExit -eq 0) { throw 'Production accepted the shipped database placeholder.' }
    if ($originProbeExit -eq 0) { throw 'Production accepted the shipped origin placeholder.' }

    $env:EMC_ALLOWED_ORIGINS = 'https://emc.example.test'
    & php tests\bootstrap-config.php | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'A complete production configuration was rejected.' }
  }
  finally {
    $ErrorActionPreference = 'Stop'
    foreach ($environmentName in @('EMC_APP_ENV', 'EMC_DB_HOST', 'EMC_DB_NAME', 'EMC_DB_USER', 'EMC_DB_PASS', 'EMC_ALLOWED_ORIGINS', 'EMC_APP_KEY')) {
      Remove-Item "Env:$environmentName" -ErrorAction SilentlyContinue
    }
  }

  & node -e "import('./src/i18n/translations.js').then(({translations})=>{const en=Object.keys(translations.en),mm=Object.keys(translations.mm);if(en.length!==mm.length||en.some(k=>!(k in translations.mm))||mm.some(k=>!(k in translations.en)))process.exit(1)})"
  if ($LASTEXITCODE -ne 0) { throw 'English and Myanmar translation keys do not match.' }

  $manifest = Get-Content public\manifest.webmanifest -Raw | ConvertFrom-Json
  if ($manifest.short_name -ne 'EMC' -or $manifest.display -ne 'standalone') { throw 'PWA manifest identity is invalid.' }
  Add-Type -AssemblyName System.Drawing
  $expectedIcons = @{
    'public\icon-192.png' = 192
    'public\icon-512.png' = 512
    'public\maskable-512.png' = 512
    'public\apple-touch-icon.png' = 180
  }
  foreach ($entry in $expectedIcons.GetEnumerator()) {
    $image = [System.Drawing.Image]::FromFile((Join-Path $projectRoot $entry.Key))
    try {
      if ($image.Width -ne $entry.Value -or $image.Height -ne $entry.Value) { throw "Invalid icon dimensions: $($entry.Key)" }
    } finally { $image.Dispose() }
  }

  $worker = Get-Content public\sw.js -Raw
  if (-not $worker.Contains('requestUrl.pathname.includes("/api/")') -or -not $worker.Contains('event.request.method !== "GET"')) {
    throw 'Service worker no longer excludes private API or mutation traffic.'
  }
  $orderLibrary = Get-Content api\lib\Orders.php -Raw
  if (-not $orderLibrary.Contains("header('Cache-Control: no-store, private')")) {
    throw 'Authenticated order photos must not be stored in browser caches.'
  }
  Get-ChildItem database\migrations -Filter '*.sql' | ForEach-Object {
    $migrationSql = Get-Content $_.FullName -Raw
    if ($migrationSql -match '(?i)ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS') {
      throw "Migration uses MariaDB-only ADD COLUMN syntax instead of the MySQL-compatible conditional pattern: $($_.Name)"
    }
    if ($migrationSql -match '(?im)^\s*(CREATE\s+DATABASE|USE\s+)') {
      throw "Migration assumes permission to create or select a hard-coded database: $($_.Name)"
    }
  }
  foreach ($required in @('RELEASE_CHECKLIST.md', 'docs\CONTENT_WORKSHEET.md', 'docs\ACCEPTANCE_TEST.md', 'docs\DEPLOYMENT.md', 'docs\OPERATIONS.md', 'docs\HANDOVER.md', 'database\migrations\004_add_order_idempotency.sql', 'api\cli\migrate.php', 'api\config.production.example.php', 'hosting\shared-hosting.htaccess', 'scripts\deploy-release.php', 'dist\.release.json', 'dist\.htaccess', 'dist\api\index.php', 'dist\api\cli\migrations\004_add_order_idempotency.sql', 'dist\storage\.htaccess')) {
    if (-not (Test-Path -LiteralPath $required)) { throw "Required release file is missing: $required" }
  }
  if (Test-Path -LiteralPath 'dist\api\config.local.php') { throw 'A local database configuration leaked into the release package.' }
  $releaseMetadata = Get-Content dist\.release.json -Raw | ConvertFrom-Json
  $packageMetadata = Get-Content package.json -Raw | ConvertFrom-Json
  if ($releaseMetadata.version -ne $packageMetadata.version -or $releaseMetadata.shortName -ne 'EMC') {
    throw 'The shared-hosting release metadata is stale or has the wrong app identity.'
  }
  $builtIndex = Get-Content dist\index.html -Raw
  if ($builtIndex -match '(?:src|href)="/(?!/)') { throw 'The production HTML contains a document-root asset URL and is not subfolder-safe.' }
  $apiSourceRoot = [System.IO.Path]::GetFullPath((Join-Path $projectRoot 'api')).TrimEnd([System.IO.Path]::DirectorySeparatorChar) + [System.IO.Path]::DirectorySeparatorChar
  foreach ($sourceFile in @(Get-ChildItem api -Recurse -File | Where-Object Name -ne 'config.local.php')) {
    if (-not $sourceFile.FullName.StartsWith($apiSourceRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
      throw "API source escaped its expected root: $($sourceFile.FullName)"
    }
    $relativePath = $sourceFile.FullName.Substring($apiSourceRoot.Length)
    $packagedFile = Join-Path (Join-Path $projectRoot 'dist\api') $relativePath
    if (-not (Test-Path -LiteralPath $packagedFile) -or (Get-FileHash $sourceFile.FullName).Hash -ne (Get-FileHash $packagedFile).Hash) {
      throw "Packaged API is missing or stale: $relativePath"
    }
  }
  foreach ($migrationFile in @(Get-ChildItem database\migrations -Filter '*.sql' -File)) {
    $packagedMigration = Join-Path $projectRoot (Join-Path 'dist\api\cli\migrations' $migrationFile.Name)
    if (-not (Test-Path -LiteralPath $packagedMigration) -or (Get-FileHash $migrationFile.FullName).Hash -ne (Get-FileHash $packagedMigration).Hash) {
      throw "Packaged migration is missing or stale: $($migrationFile.Name)"
    }
  }
  if ((Get-FileHash 'hosting\shared-hosting.htaccess').Hash -ne (Get-FileHash 'dist\.htaccess').Hash) {
    throw 'The deployed Apache rules are stale.'
  }
  if (Test-Path -LiteralPath 'dist\database') { throw 'Source-style database paths leaked into the public package.' }

  $deploymentAuditRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("emc-deploy-audit-" + [guid]::NewGuid().ToString('N'))
  $deploymentTarget = Join-Path $deploymentAuditRoot 'shared\projects\emc'
  try {
    & php scripts\deploy-release.php $deploymentTarget | Out-Null
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath (Join-Path $deploymentTarget 'api\index.php'))) {
      throw 'The shared-hosting deployment command failed.'
    }
    $configSentinel = Join-Path $deploymentTarget 'api\config.local.php'
    $photoSentinel = Join-Path $deploymentTarget 'storage\order-photos\release-check.txt'
    [System.IO.File]::WriteAllText($configSentinel, '<?php return [''sentinel'' => ''preserve''];')
    [System.IO.File]::WriteAllText($photoSentinel, 'preserve-private-photo')
    $configHash = (Get-FileHash $configSentinel).Hash
    $photoHash = (Get-FileHash $photoSentinel).Hash
    & php scripts\deploy-release.php $deploymentTarget | Out-Null
    if ($LASTEXITCODE -ne 0 -or (Get-FileHash $configSentinel).Hash -ne $configHash -or (Get-FileHash $photoSentinel).Hash -ne $photoHash) {
      throw 'Redeployment did not preserve server configuration and private photos.'
    }
  }
  finally {
    if (Test-Path -LiteralPath $deploymentAuditRoot) {
      $verifiedAuditRoot = [System.IO.Path]::GetFullPath($deploymentAuditRoot)
      $temporaryRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath()).TrimEnd([System.IO.Path]::DirectorySeparatorChar) + [System.IO.Path]::DirectorySeparatorChar
      if (-not $verifiedAuditRoot.StartsWith($temporaryRoot, [System.StringComparison]::OrdinalIgnoreCase) -or (Split-Path $verifiedAuditRoot -Leaf) -notmatch '^emc-deploy-audit-[a-f0-9]{32}$') {
        throw "Unsafe deployment-audit cleanup path: $verifiedAuditRoot"
      }
      [System.IO.Directory]::Delete($verifiedAuditRoot, $true)
    }
  }
  & git ls-files --error-unmatch dist\index.html | Out-Null
  if ($LASTEXITCODE -ne 0) { throw 'The SSH-ready dist package is not tracked by Git.' }
  & git diff --exit-code -- dist | Out-Null
  if ($LASTEXITCODE -ne 0) { throw 'The committed dist package is stale; run npm run build and stage dist.' }
  & git diff --check
  if ($LASTEXITCODE -ne 0) { throw 'Git whitespace check failed.' }
  Write-Output 'PASS release static checks'
}
finally {
  Pop-Location
}
