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
  foreach ($required in @('RELEASE_CHECKLIST.md', 'docs\CONTENT_WORKSHEET.md', 'docs\DEPLOYMENT.md', 'docs\OPERATIONS.md', 'docs\HANDOVER.md', 'database\migrations\004_add_order_idempotency.sql')) {
    if (-not (Test-Path -LiteralPath $required)) { throw "Required release file is missing: $required" }
  }
  & git diff --check
  if ($LASTEXITCODE -ne 0) { throw 'Git whitespace check failed.' }
  Write-Output 'PASS release static checks'
}
finally {
  Pop-Location
}
