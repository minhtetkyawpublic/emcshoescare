[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
Push-Location $projectRoot
try {
  & npm.cmd run lint
  if ($LASTEXITCODE -ne 0) { throw 'React lint failed.' }
  & npm.cmd run build
  if ($LASTEXITCODE -ne 0) { throw 'Laravel Vite build failed.' }
  & npm.cmd audit --omit=dev
  if ($LASTEXITCODE -ne 0) { throw 'Production JavaScript dependency audit failed.' }

  & composer validate --strict --no-interaction
  if ($LASTEXITCODE -ne 0) { throw 'Composer metadata is invalid.' }
  $composerCommand = Get-Command composer -CommandType Application -ErrorAction Stop | Select-Object -First 1
  $composerAudit = Start-Process -FilePath $composerCommand.Source -ArgumentList @('audit', '--no-dev', '--no-interaction') -NoNewWindow -Wait -PassThru
  if ($composerAudit.ExitCode -ne 0) { throw 'Production Composer dependency audit failed.' }
  & php vendor\bin\pint --test
  if ($LASTEXITCODE -ne 0) { throw 'Laravel formatting check failed.' }
  & php artisan route:list | Out-Null
  if ($LASTEXITCODE -ne 0) { throw 'Laravel routes could not be loaded.' }
  & php artisan test
  if ($LASTEXITCODE -ne 0) { throw 'Laravel feature tests failed.' }

  $phpRoots = @('app', 'bootstrap', 'config', 'database', 'routes', 'tests')
  foreach ($phpRoot in $phpRoots) {
    Get-ChildItem $phpRoot -Recurse -Filter '*.php' | ForEach-Object {
      & php -l $_.FullName | Out-Null
      if ($LASTEXITCODE -ne 0) { throw "PHP syntax check failed: $($_.FullName)" }
    }
  }
  foreach ($phpFile in @('artisan', 'index.php', 'public\index.php')) {
    & php -l $phpFile | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "PHP syntax check failed: $phpFile" }
  }

  & node -e "import('./resources/js/i18n/translations.js').then(({translations})=>{const en=Object.keys(translations.en),mm=Object.keys(translations.mm);if(en.length!==mm.length||en.some(k=>!(k in translations.mm))||mm.some(k=>!(k in translations.en)))process.exit(1)})"
  if ($LASTEXITCODE -ne 0) { throw 'English and Myanmar translation keys do not match.' }
  & node -e "import('./resources/js/api/baseUrl.js').then(({apiBaseFromModuleUrl:f,appBaseFromModuleUrl:b})=>{const cases=[['https://example.com/build/assets/app.js','/api',''],['https://example.com/emcshoescare/build/assets/app.js','/emcshoescare/api','/emcshoescare'],['https://example.com/clients/shoes/build/assets/app.js','/clients/shoes/api','/clients/shoes'],['http://localhost:5173/resources/js/api/client.js','/api','']];if(cases.some(([url,api,base])=>f(url)!==api||b(url)!==base))process.exit(1)})"
  if ($LASTEXITCODE -ne 0) { throw 'React API/PWA URLs are not portable across deployment directories.' }

  $manifest = Get-Content public\manifest.webmanifest -Raw | ConvertFrom-Json
  $adminManifest = Get-Content public\manifest-admin.webmanifest -Raw | ConvertFrom-Json
  if ($manifest.short_name -ne 'EMC' -or $manifest.display -ne 'standalone' -or $manifest.id -ne './customer/' -or $manifest.start_url -ne './customer/home' -or $manifest.scope -ne './customer/') { throw 'Customer PWA manifest identity is invalid.' }
  if ($adminManifest.short_name -ne 'EMC Admin' -or $adminManifest.display -ne 'standalone' -or $adminManifest.id -ne './admin/' -or $adminManifest.start_url -ne './admin/orders' -or $adminManifest.scope -ne './admin/') { throw 'Admin PWA manifest identity is invalid.' }
  Add-Type -AssemblyName System.Drawing
  $expectedIcons = @{
    'public\emc-pwa-v2-192.png' = 192
    'public\emc-pwa-v2-512.png' = 512
    'public\emc-pwa-v2-maskable-512.png' = 512
    'public\apple-touch-icon-v2.png' = 180
  }
  foreach ($entry in $expectedIcons.GetEnumerator()) {
    $image = [System.Drawing.Image]::FromFile((Join-Path $projectRoot $entry.Key))
    try {
      if ($image.Width -ne $entry.Value -or $image.Height -ne $entry.Value) { throw "Invalid icon dimensions: $($entry.Key)" }
    } finally { $image.Dispose() }
  }
  $worker = Get-Content public\sw.js -Raw
  if (-not $worker.Contains('requestUrl.pathname.includes("/api/")') -or -not $worker.Contains('event.request.method !== "GET"')) { throw 'Service worker no longer excludes private API or mutation traffic.' }
  $photoController = Get-Content app\Http\Controllers\OrderController.php -Raw
  if (-not $photoController.Contains("'Cache-Control' => 'no-store, private'")) { throw 'Authenticated photos must not be stored in browser caches.' }

  $required = @(
    'artisan', 'composer.json', 'composer.lock', '.env.production.example', 'index.php', '.htaccess',
    'app\Providers\AppServiceProvider.php', 'database\migrations\2026_08_14_000000_create_emc_schema.php',
    'resources\views\app.blade.php', 'resources\js\main.jsx', 'vite.config.js',
    'public\index.php', 'public\.htaccess', 'public\build\manifest.json',
    'docs\DEPLOYMENT.md', 'docs\OPERATIONS.md'
  )
  foreach ($path in $required) {
    if (-not (Test-Path -LiteralPath $path)) { throw "Required unified-app file is missing: $path" }
  }
  foreach ($obsolete in @('backend', 'dist', 'hosting', 'index.html', 'scripts\deploy-release.php', 'scripts\package-release.mjs', 'public\api')) {
    if (Test-Path -LiteralPath $obsolete) { throw "Obsolete split-project material remains: $obsolete" }
  }

  $viteManifest = Get-Content public\build\manifest.json -Raw | ConvertFrom-Json
  $entry = $viteManifest.PSObject.Properties['resources/js/main.jsx'].Value
  if (-not $entry -or -not $entry.isEntry -or -not $entry.file.StartsWith('assets/')) { throw 'Vite manifest does not contain the React application entry.' }
  $rootRules = Get-Content .htaccess -Raw
  if (-not $rootRules.Contains('RewriteRule ^ index.php [L]') -or -not $rootRules.Contains('public/$0')) { throw 'Shared-hosting root routing is not protected or Laravel-first.' }
  $routes = (& php artisan route:list --json | ConvertFrom-Json)
  if (-not ($routes | Where-Object { $_.uri -eq 'api/health' }) -or -not ($routes | Where-Object { $_.name -eq 'spa' })) { throw 'Unified API or SPA routes are missing.' }

  # A fresh CI checkout has no .env. Force a filesystem cache while exercising
  # optimization so optimize:clear never depends on an un-migrated database.
  $previousCacheStore = $env:CACHE_STORE
  try {
    $env:CACHE_STORE = 'file'
    & php artisan optimize
    if ($LASTEXITCODE -ne 0) { throw 'Laravel production optimization failed.' }
    & php artisan optimize:clear
    if ($LASTEXITCODE -ne 0) { throw 'Laravel optimization cleanup failed.' }
  } finally {
    if ($null -eq $previousCacheStore) {
      Remove-Item Env:CACHE_STORE -ErrorAction SilentlyContinue
    } else {
      $env:CACHE_STORE = $previousCacheStore
    }
  }

  & git ls-files --error-unmatch public/build/manifest.json | Out-Null
  if ($LASTEXITCODE -ne 0) { throw 'The Hostinger-ready Vite build is not tracked by Git.' }
  & git diff --exit-code -- public/build | Out-Null
  if ($LASTEXITCODE -ne 0) { throw 'The committed Vite build is stale; run npm run build and stage public/build.' }
  & git diff --check
  if ($LASTEXITCODE -ne 0) { throw 'Git whitespace check failed.' }
  Write-Output 'PASS unified Laravel + React + Vite PWA release checks'
}
finally {
  Pop-Location
}
