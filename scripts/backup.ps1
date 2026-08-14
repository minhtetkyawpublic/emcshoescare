[CmdletBinding()]
param(
  [string]$BackupRoot = (Join-Path ([Environment]::GetFolderPath('LocalApplicationData')) 'EMC\backups'),
  [string]$MySqlBin = 'D:\xampp\mysql\bin',
  [string]$Database = 'emc_shoes_care',
  [string]$User = 'root',
  [string]$HostName = '127.0.0.1',
  [int]$Port = 3306,
  [string]$DefaultsExtraFile = ''
)

$ErrorActionPreference = 'Stop'
$projectRoot = [System.IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot))
$photoRoot = Join-Path $projectRoot 'storage\app\private\order-photos'
if (-not (Test-Path -LiteralPath $photoRoot)) { New-Item -ItemType Directory -Path $photoRoot -Force | Out-Null }
$backupRootFull = [System.IO.Path]::GetFullPath($BackupRoot)
$stamp = [DateTime]::UtcNow.ToString('yyyyMMdd-HHmmss')
$stage = Join-Path $backupRootFull "emc-$stamp"
$archive = "$stage.zip"

New-Item -ItemType Directory -Path $stage -Force | Out-Null
try {
  $dumpPath = Join-Path $stage 'database.sql'
  $dumpTool = Join-Path $MySqlBin 'mysqldump.exe'
  if (-not (Test-Path -LiteralPath $dumpTool)) { throw "mysqldump was not found at $dumpTool" }
  $dumpArgs = @()
  if ($DefaultsExtraFile) {
    $credentialsPath = [System.IO.Path]::GetFullPath($DefaultsExtraFile)
    if (-not (Test-Path -LiteralPath $credentialsPath)) { throw 'The MySQL credentials file does not exist.' }
    $dumpArgs += "--defaults-extra-file=$credentialsPath"
  }
  $dumpArgs += @('--single-transaction', '--quick', '--routines', '--triggers', '--events', '--hex-blob', '--default-character-set=utf8mb4', "--host=$HostName", "--port=$Port", "--user=$User", "--result-file=$dumpPath", $Database)
  & $dumpTool @dumpArgs
  if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $dumpPath)) { throw 'The MySQL backup failed.' }

  $photoDestination = Join-Path $stage 'order-photos'
  New-Item -ItemType Directory -Path $photoDestination -Force | Out-Null
  Get-ChildItem -LiteralPath $photoRoot -Force | Where-Object { $_.Name -ne '.gitkeep' } | Copy-Item -Destination $photoDestination -Recurse -Force
  $metadata = [ordered]@{
    app = 'EMC Shoes Care Myanmar'
    createdAtUtc = [DateTime]::UtcNow.ToString('o')
    database = $Database
    photoFiles = @(Get-ChildItem -LiteralPath $photoDestination -Recurse -File).Count
  }
  $metadata | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $stage 'backup.json') -Encoding UTF8
  Compress-Archive -LiteralPath (Join-Path $stage 'database.sql'), (Join-Path $stage 'backup.json'), $photoDestination -DestinationPath $archive -CompressionLevel Optimal -Force
  $hash = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant()
  "$hash  $([System.IO.Path]::GetFileName($archive))" | Set-Content -LiteralPath "$archive.sha256" -Encoding ASCII
  Write-Output $archive
  Write-Output "SHA256 $hash"
}
finally {
  $verifiedStage = [System.IO.Path]::GetFullPath($stage)
  $requiredPrefix = $backupRootFull.TrimEnd([System.IO.Path]::DirectorySeparatorChar) + [System.IO.Path]::DirectorySeparatorChar
  if ($verifiedStage.StartsWith($requiredPrefix, [System.StringComparison]::OrdinalIgnoreCase) -and (Test-Path -LiteralPath $verifiedStage)) {
    Remove-Item -LiteralPath $verifiedStage -Recurse -Force
  }
}
