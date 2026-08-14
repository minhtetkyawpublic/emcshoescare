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

  Push-Location backend
  try {
    & composer validate --strict --no-interaction
    if ($LASTEXITCODE -ne 0) { throw 'Laravel Composer metadata is invalid.' }
    # Composer writes its successful audit summary to stderr on some Windows
    # installations. Run the resolved executable directly so PowerShell does not
    # promote that native stderr message to a terminating error.
    $composerCommand = Get-Command composer -CommandType Application -ErrorAction Stop | Select-Object -First 1
    $composerAudit = Start-Process -FilePath $composerCommand.Source -ArgumentList @('audit', '--no-dev', '--no-interaction') -NoNewWindow -Wait -PassThru
    if ($composerAudit.ExitCode -ne 0) { throw 'Laravel production dependency audit failed.' }
    & php vendor\bin\pint --test
    if ($LASTEXITCODE -ne 0) { throw 'Laravel formatting check failed.' }
    & php artisan route:list | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Laravel routes could not be loaded.' }
    & php artisan test
    if ($LASTEXITCODE -ne 0) { throw 'Laravel feature tests failed.' }
  }
  finally { Pop-Location }

  $phpRoots = @('backend\app', 'backend\bootstrap', 'backend\config', 'backend\database', 'backend\routes')
  foreach ($phpRoot in $phpRoots) {
    Get-ChildItem $phpRoot -Recurse -Filter '*.php' | ForEach-Object {
      & php -l $_.FullName | Out-Null
      if ($LASTEXITCODE -ne 0) { throw "PHP syntax check failed: $($_.FullName)" }
    }
  }
  foreach ($phpFile in @('backend\artisan', 'hosting\laravel-api-index.php', 'scripts\deploy-release.php')) {
    & php -l $phpFile | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "PHP syntax check failed: $phpFile" }
  }

  & node -e "import('./src/i18n/translations.js').then(({translations})=>{const en=Object.keys(translations.en),mm=Object.keys(translations.mm);if(en.length!==mm.length||en.some(k=>!(k in translations.mm))||mm.some(k=>!(k in translations.en)))process.exit(1)})"
  if ($LASTEXITCODE -ne 0) { throw 'English and Myanmar translation keys do not match.' }
  & node -e "import('./src/api/baseUrl.js').then(({apiBaseFromModuleUrl:f})=>{const cases=[['https://example.com/assets/app.js','/api'],['https://example.com/emcshoescare/assets/app.js','/emcshoescare/api'],['https://example.com/clients/shoes/assets/app.js','/clients/shoes/api'],['http://localhost:5173/src/api/client.js','/api']];if(cases.some(([url,want])=>f(url)!==want))process.exit(1)})"
  if ($LASTEXITCODE -ne 0) { throw 'Frontend API URLs are not portable across deployment directories.' }

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
  $photoController = Get-Content backend\app\Http\Controllers\OrderController.php -Raw
  if (-not $photoController.Contains("'Cache-Control' => 'no-store, private'")) {
    throw 'Authenticated order photos must not be stored in browser caches.'
  }

  $required = @(
    'backend\artisan', 'backend\composer.json', 'backend\composer.lock', 'backend\.env.production.example',
    'backend\resources\views\.gitkeep',
    'backend\database\migrations\2026_08_14_000000_create_emc_schema.php',
    'hosting\shared-hosting.htaccess', 'hosting\laravel-api.htaccess', 'hosting\laravel-api-index.php',
    'scripts\deploy-release.php', 'docs\DEPLOYMENT.md', 'docs\OPERATIONS.md',
    'dist\.release.json', 'dist\.htaccess', 'dist\api\.htaccess', 'dist\api\index.php'
  )
  foreach ($path in $required) {
    if (-not (Test-Path -LiteralPath $path)) { throw "Required release file is missing: $path" }
  }
  foreach ($forbidden in @('dist\api\runtime.php', 'dist\vendor', 'dist\.env', 'dist\storage', 'dist\database')) {
    if (Test-Path -LiteralPath $forbidden) { throw "Private Laravel material leaked into the public package: $forbidden" }
  }

  $releaseMetadata = Get-Content dist\.release.json -Raw | ConvertFrom-Json
  $packageMetadata = Get-Content package.json -Raw | ConvertFrom-Json
  if ($releaseMetadata.version -ne $packageMetadata.version -or $releaseMetadata.shortName -ne 'EMC' -or $releaseMetadata.framework -ne 'Laravel 12') {
    throw 'The shared-hosting release metadata is stale or has the wrong framework identity.'
  }
  $builtIndex = Get-Content dist\index.html -Raw
  if ($builtIndex -match '(?:src|href)="/(?!/)') { throw 'The production HTML contains a document-root asset URL and is not subfolder-safe.' }
  if ((Get-FileHash 'hosting\shared-hosting.htaccess').Hash -ne (Get-FileHash 'dist\.htaccess').Hash) { throw 'The deployed Apache rules are stale.' }
  if ((Get-FileHash 'hosting\laravel-api.htaccess').Hash -ne (Get-FileHash 'dist\api\.htaccess').Hash) { throw 'The deployed Laravel API rules are stale.' }
  if ((Get-FileHash 'hosting\laravel-api-index.php').Hash -ne (Get-FileHash 'dist\api\index.php').Hash) { throw 'The deployed Laravel API bridge is stale.' }

  $deploymentAuditRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("emc-deploy-audit-" + [guid]::NewGuid().ToString('N'))
  $deploymentTarget = Join-Path $deploymentAuditRoot 'shared\projects\emc'
  try {
    & php scripts\deploy-release.php $deploymentTarget | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'The Laravel shared-hosting deployment command failed.' }
    $runtimeFile = Join-Path $deploymentTarget 'api\runtime.php'
    $runtime = & php -r "`$path=require '$($runtimeFile.Replace('\', '/'))'; echo `$path;"
    $expectedRuntime = [System.IO.Path]::GetFullPath((Join-Path $projectRoot 'backend'))
    if (-not (Test-Path -LiteralPath (Join-Path $deploymentTarget 'api\index.php')) -or [System.IO.Path]::GetFullPath($runtime) -ne $expectedRuntime) {
      throw 'The public API bridge does not reference the private Laravel runtime.'
    }
    $sentinel = Join-Path $deploymentTarget 'hostinger-user-file.txt'
    [System.IO.File]::WriteAllText($sentinel, 'preserve-target-file')
    $sentinelHash = (Get-FileHash $sentinel).Hash
    & php scripts\deploy-release.php $deploymentTarget | Out-Null
    if ($LASTEXITCODE -ne 0 -or (Get-FileHash $sentinel).Hash -ne $sentinelHash) { throw 'Redeployment did not preserve unrelated target files.' }
  }
  finally {
    if (Test-Path -LiteralPath $deploymentAuditRoot) {
      $verified = [System.IO.Path]::GetFullPath($deploymentAuditRoot)
      $temporaryRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath()).TrimEnd([System.IO.Path]::DirectorySeparatorChar) + [System.IO.Path]::DirectorySeparatorChar
      if (-not $verified.StartsWith($temporaryRoot, [System.StringComparison]::OrdinalIgnoreCase) -or (Split-Path $verified -Leaf) -notmatch '^emc-deploy-audit-[a-f0-9]{32}$') {
        throw "Unsafe deployment-audit cleanup path: $verified"
      }
      [System.IO.Directory]::Delete($verified, $true)
    }
  }

  & git ls-files --error-unmatch dist\index.html | Out-Null
  if ($LASTEXITCODE -ne 0) { throw 'The SSH-ready frontend package is not tracked by Git.' }
  & git diff --exit-code -- dist | Out-Null
  if ($LASTEXITCODE -ne 0) { throw 'The committed dist package is stale; run npm run build and stage dist.' }
  & git diff --check
  if ($LASTEXITCODE -ne 0) { throw 'Git whitespace check failed.' }
  Write-Output 'PASS Laravel release checks'
}
finally {
  Pop-Location
}
