[CmdletBinding()]
param(
  [Parameter(Mandatory = $true)][string]$AdminPassword,
  [string]$AdminUsername = 'emcadmin',
  [string]$BaseUrl = 'http://127.0.0.1/emcshoecare/api',
  [string]$MySqlBin = 'D:\xampp\mysql\bin',
  [string]$MySqlClient = '',
  [string]$MySqlHost = '',
  [string]$CurlClient = '',
  [string]$PhotoPath = (Join-Path (Split-Path -Parent $PSScriptRoot) 'public\icon-192.png')
)

$ErrorActionPreference = 'Stop'
$runningOnWindows = [System.Environment]::OSVersion.Platform -eq [System.PlatformID]::Win32NT
if ([string]::IsNullOrWhiteSpace($MySqlClient)) {
  $MySqlClient = if ($runningOnWindows) { Join-Path $MySqlBin 'mysql.exe' } else { 'mysql' }
}
if ([string]::IsNullOrWhiteSpace($CurlClient)) {
  $CurlClient = if ($runningOnWindows) { 'curl.exe' } else { 'curl' }
}
$customerId = 0
$otherCustomerId = 0
$orderId = 0
$dropoffOrderId = 0
$testPackageId = 0
$originalPickupFee = 0
$pickupFeeChanged = $false
$storageRecords = @()

function Assert-True([bool]$Condition, [string]$Message) {
  if (-not $Condition) { throw "Assertion failed: $Message" }
}

function Invoke-TestMySql {
  param([string[]]$ClientArguments)
  $connectionArguments = @('-u', 'root')
  if (-not [string]::IsNullOrWhiteSpace($MySqlHost)) {
    $connectionArguments += @('--protocol=tcp', "--host=$MySqlHost")
  }
  return & $MySqlClient @connectionArguments @ClientArguments
}

function Invoke-JsonApi {
  param([string]$Method, [string]$Path, $Session, $Body = $null, [string]$Csrf = '')
  $parameters = @{ Uri = "$BaseUrl$Path"; Method = $Method; WebSession = $Session }
  if ($Csrf) { $parameters.Headers = @{ 'X-CSRF-Token' = $Csrf } }
  if ($null -ne $Body) {
    $parameters.ContentType = 'application/json'
    $parameters.Body = $Body | ConvertTo-Json -Depth 10 -Compress
  }
  return Invoke-RestMethod @parameters
}

function SessionCookieHeader($Session) {
  $uri = [Uri]$BaseUrl
  return (($Session.Cookies.GetCookies($uri) | ForEach-Object { "$($_.Name)=$($_.Value)" }) -join '; ')
}

try {
  Assert-True (Test-Path -LiteralPath $PhotoPath) 'The test photo exists.'
  $adminSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  $adminLogin = Invoke-JsonApi 'POST' '/admin/auth/login' $adminSession @{ username = $AdminUsername; password = $AdminPassword }
  $adminCsrf = $adminLogin.data.csrfToken
  $adminCheck = Invoke-JsonApi 'GET' '/admin/auth/session' $adminSession
  Assert-True $adminCheck.data.authenticated 'Admin session persists.'
  $adminCsrf = $adminCheck.data.csrfToken
  Write-Output 'PASS phase=admin-authentication'

  $settings = Invoke-JsonApi 'GET' '/admin/settings' $adminSession
  $originalPickupFee = [int]$settings.data.pickupFeeKs
  $testPickupFee = $originalPickupFee + 500
  $updatedSettings = Invoke-JsonApi 'PUT' '/admin/settings' $adminSession @{ pickupFeeKs = $testPickupFee } $adminCsrf
  $adminCsrf = $updatedSettings.data.csrfToken
  $pickupFeeChanged = $true
  Assert-True ([int]$updatedSettings.data.pickupFeeKs -eq $testPickupFee) 'Pickup fee can be updated.'

  $createdPackage = Invoke-JsonApi 'POST' '/admin/packages' $adminSession @{
    nameEn = 'Release Test Package'; nameMm = 'MM Release Test Package';
    descriptionEn = 'Temporary release workflow package.'; descriptionMm = 'MM temporary release workflow package.';
    priceKs = 12345; sortOrder = 9999; active = $false
  } $adminCsrf
  $testPackageId = [int]$createdPackage.data.id
  $adminCsrf = $createdPackage.data.csrfToken
  $updatedPackage = Invoke-JsonApi 'PUT' "/admin/packages/$testPackageId" $adminSession @{
    nameEn = 'Release Test Package Updated'; nameMm = 'MM Release Test Package Updated';
    descriptionEn = 'Temporary release workflow package, updated.'; descriptionMm = 'MM temporary release workflow package, updated.';
    priceKs = 12500; sortOrder = 9999; active = $true
  } $adminCsrf
  $adminCsrf = $updatedPackage.data.csrfToken
  $archivedPackage = Invoke-JsonApi 'DELETE' "/admin/packages/$testPackageId" $adminSession $null $adminCsrf
  $adminCsrf = $archivedPackage.data.csrfToken
  Write-Output 'PASS phase=package-and-settings-management'

  $customerSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  $phone = '099' + (Get-Random -Minimum 1000000 -Maximum 9999999)
  $registration = Invoke-JsonApi 'POST' '/auth/register' $customerSession @{
    phone = $phone; password = 'ReleaseTestPassword9'; fullName = 'Release Test Customer';
    address = 'Yangon release test address'; remember = $true
  }
  $customerId = [int]$registration.data.customer.id
  $customerCsrf = $registration.data.csrfToken
  $rememberCookie = $customerSession.Cookies.GetCookies([Uri]$BaseUrl) | Select-Object -First 1
  Assert-True ($rememberCookie.Expires -gt [DateTime]::Now.AddDays(20)) 'Remembered customer cookie has a persistent expiry.'
  $customerCheck = Invoke-JsonApi 'GET' '/auth/session' $customerSession
  Assert-True ($customerCheck.data.authenticated -and [int]$customerCheck.data.customer.id -eq $customerId) 'Remembered customer session restores the same account.'
  $customerCsrf = $customerCheck.data.csrfToken
  Write-Output 'PASS phase=remembered-customer-authentication'

  $packages = Invoke-JsonApi 'GET' '/packages' $customerSession
  $package = $packages.data.packages | Select-Object -First 1
  Assert-True ($null -ne $package) 'At least one active package is available.'
  $requestId = [guid]::NewGuid().ToString()
  $cookieHeader = SessionCookieHeader $customerSession
  $multipart = @('-sS', '-b', $cookieHeader, '-H', "X-CSRF-Token: $customerCsrf",
    '-F', "clientRequestId=$requestId", '-F', 'fullName=Release Test Customer',
    '-F', 'address=Yangon release test address', '-F', "packageId=$($package.id)",
    '-F', 'handover=pickup', '-F', 'notes=Full release workflow test')
  1..10 | ForEach-Object { $multipart += @('-F', "photos[]=@$PhotoPath;type=image/png") }
  $firstRaw = & $CurlClient @multipart "$BaseUrl/orders"
  $firstOrder = $firstRaw | ConvertFrom-Json
  Assert-True $firstOrder.success "Order creation succeeds: $firstRaw"
  $orderId = [int]$firstOrder.data.order.id
  $customerCsrf = $firstOrder.data.csrfToken
  Assert-True ([int]$firstOrder.data.order.pickupFeeKs -eq $testPickupFee) 'Pickup fee is included only in the pickup order.'
  Assert-True ([int]$firstOrder.data.order.photoCount -eq 10) 'Order stores the maximum of ten photos.'

  $multipart[4] = "X-CSRF-Token: $customerCsrf"
  $replayRaw = & $CurlClient @multipart "$BaseUrl/orders"
  $replay = $replayRaw | ConvertFrom-Json
  Assert-True ($replay.success -and $replay.data.replayed -and [int]$replay.data.order.id -eq $orderId) 'Retry returns the original order without duplication.'
  $customerCsrf = $replay.data.csrfToken

  $dropoffRequestId = [guid]::NewGuid().ToString()
  $dropoffMultipart = @('-sS', '-b', $cookieHeader, '-H', "X-CSRF-Token: $customerCsrf",
    '-F', "clientRequestId=$dropoffRequestId", '-F', 'fullName=Release Test Customer',
    '-F', 'address=Yangon release test address', '-F', "packageId=$($package.id)",
    '-F', 'handover=dropoff', '-F', 'notes=Drop-off release workflow test',
    '-F', "photos[]=@$PhotoPath;type=image/png")
  $dropoffRaw = & $CurlClient @dropoffMultipart "$BaseUrl/orders"
  $dropoffOrder = $dropoffRaw | ConvertFrom-Json
  Assert-True $dropoffOrder.success "Drop-off order creation succeeds: $dropoffRaw"
  $dropoffOrderId = [int]$dropoffOrder.data.order.id
  $customerCsrf = $dropoffOrder.data.csrfToken
  Assert-True ([int]$dropoffOrder.data.order.pickupFeeKs -eq 0) 'Drop-off order does not include the optional pickup fee.'
  Write-Output 'PASS phase=order-creation-and-retry'

  $adminOrder = Invoke-JsonApi 'GET' "/admin/orders/$orderId" $adminSession
  Assert-True ([int]$adminOrder.data.order.photoCount -eq 10) 'Admin can view all ten order photos.'
  $photoId = [int]$adminOrder.data.order.photos[0].id
  $adminPhoto = Invoke-WebRequest -Uri "$BaseUrl/orders/$orderId/photos/$photoId" -WebSession $adminSession -UseBasicParsing
  $adminPhotoContentType = ($adminPhoto.Headers['Content-Type'] -join ',')
  $adminPhotoCacheControl = ($adminPhoto.Headers['Cache-Control'] -join ',')
  Assert-True ($adminPhoto.StatusCode -eq 200 -and $adminPhotoContentType -match '^image/png(?:;|$)') 'Admin can retrieve the private photo.'
  Assert-True ($adminPhotoCacheControl -match 'no-store') 'Private photo responses cannot be stored in browser caches.'

  foreach ($status in @('confirmed', 'pickup_scheduled', 'rider_on_way', 'shoes_received', 'repairing', 'ready', 'done')) {
    $statusResult = Invoke-JsonApi 'PUT' "/admin/orders/$orderId/status" $adminSession @{
      status = $status; noteEn = "Release test: $status"; noteMm = "MM release test: $status"
    } $adminCsrf
    $adminCsrf = $statusResult.data.csrfToken
    Assert-True ($statusResult.data.order.status -eq $status) "Status advances to $status."
  }
  foreach ($status in @('confirmed', 'shoes_received', 'repairing', 'ready', 'done')) {
    $statusResult = Invoke-JsonApi 'PUT' "/admin/orders/$dropoffOrderId/status" $adminSession @{
      status = $status; noteEn = "Drop-off release test: $status"; noteMm = "MM drop-off release test: $status"
    } $adminCsrf
    $adminCsrf = $statusResult.data.csrfToken
    Assert-True ($statusResult.data.order.status -eq $status) "Drop-off status advances to $status."
  }

  $customerOrders = Invoke-JsonApi 'GET' '/orders' $customerSession
  $listedOrder = $customerOrders.data.orders | Where-Object { [int]$_.id -eq $orderId }
  Assert-True ($listedOrder.status -eq 'done' -and $listedOrder.unreadStatus) 'Customer sees the final status as unread.'
  $listedDropoff = $customerOrders.data.orders | Where-Object { [int]$_.id -eq $dropoffOrderId }
  Assert-True ($listedDropoff.status -eq 'done' -and $listedDropoff.unreadStatus) 'Customer sees the final drop-off status as unread.'
  $orderDetail = Invoke-JsonApi 'GET' "/orders/$orderId" $customerSession
  Assert-True ($orderDetail.data.order.history.Count -eq 8) 'Customer timeline contains submission plus seven admin updates.'
  $dropoffDetail = Invoke-JsonApi 'GET' "/orders/$dropoffOrderId" $customerSession
  Assert-True ($dropoffDetail.data.order.history.Count -eq 6) 'Drop-off timeline skips pickup and rider states.'
  $customerPhoto = Invoke-WebRequest -Uri "$BaseUrl/orders/$orderId/photos/$photoId" -WebSession $customerSession -UseBasicParsing
  Assert-True ($customerPhoto.StatusCode -eq 200) 'Owning customer can retrieve the private photo.'
  $customerPhotoCacheControl = ($customerPhoto.Headers['Cache-Control'] -join ',')
  Assert-True ($customerPhotoCacheControl -match 'no-store') 'Owning-customer photo responses remain non-cacheable.'
  Write-Output 'PASS phase=status-history-and-private-photos'

  $otherSession = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  $otherPhone = '098' + (Get-Random -Minimum 1000000 -Maximum 9999999)
  $otherRegistration = Invoke-JsonApi 'POST' '/auth/register' $otherSession @{
    phone = $otherPhone; password = 'ReleaseTestPassword9'; fullName = 'Other Test Customer'; address = ''; remember = $false
  }
  $otherCustomerId = [int]$otherRegistration.data.customer.id
  $orderAccessRejected = $false
  try {
    Invoke-JsonApi 'GET' "/orders/$orderId" $otherSession | Out-Null
  } catch {
    $orderAccessRejected = ([int]$_.Exception.Response.StatusCode -eq 404)
  }
  Assert-True $orderAccessRejected 'A different customer cannot retrieve the order.'
  $accessRejected = $false
  try {
    Invoke-WebRequest -Uri "$BaseUrl/orders/$orderId/photos/$photoId" -WebSession $otherSession -UseBasicParsing -ErrorAction Stop | Out-Null
  } catch {
    $accessRejected = ([int]$_.Exception.Response.StatusCode -eq 404)
  }
  Assert-True $accessRejected 'A different customer cannot retrieve the private photo.'
  Write-Output 'PASS phase=cross-account-access-denial'

  $seen = Invoke-JsonApi 'POST' "/orders/$orderId/seen" $customerSession $null $customerCsrf
  $customerCsrf = $seen.data.csrfToken
  $dropoffSeen = Invoke-JsonApi 'POST' "/orders/$dropoffOrderId/seen" $customerSession $null $customerCsrf
  $customerCsrf = $dropoffSeen.data.csrfToken
  $afterSeen = Invoke-JsonApi 'GET' '/orders' $customerSession
  $seenOrder = $afterSeen.data.orders | Where-Object { [int]$_.id -eq $orderId }
  $seenDropoff = $afterSeen.data.orders | Where-Object { [int]$_.id -eq $dropoffOrderId }
  Assert-True (-not $seenOrder.unreadStatus -and -not $seenDropoff.unreadStatus) 'Customer can mark both status histories as read.'
  Write-Output 'PASS phase=unread-status-tracking'

  $restoredSettings = Invoke-JsonApi 'PUT' '/admin/settings' $adminSession @{ pickupFeeKs = $originalPickupFee } $adminCsrf
  $adminCsrf = $restoredSettings.data.csrfToken
  $pickupFeeChanged = $false
  Invoke-JsonApi 'POST' '/auth/logout' $customerSession $null $customerCsrf | Out-Null
  Invoke-JsonApi 'POST' '/admin/auth/logout' $adminSession $null $adminCsrf | Out-Null

  Write-Output "PASS customer=$customerId pickupOrder=$orderId dropoffOrder=$dropoffOrderId package=$testPackageId pickupPhotos=10 pickupHistory=8 dropoffHistory=6 crossAccount=protected privatePhoto=no-store duplicateCount=1"
}
finally {
  if ($adminSession -and $adminCsrf) {
    try { Invoke-JsonApi 'POST' '/admin/auth/logout' $adminSession $null $adminCsrf | Out-Null } catch { }
  }
  $mysql = Get-Command $MySqlClient -ErrorAction SilentlyContinue
  if ($mysql) {
    if ($pickupFeeChanged) {
      Invoke-TestMySql @('-D', 'emc_shoes_care', '-e', "UPDATE shop_settings SET setting_value='$originalPickupFee' WHERE setting_key='pickup_fee_ks';") | Out-Null
    }
    $cleanupOrderIds = @($orderId, $dropoffOrderId) | Where-Object { $_ -gt 0 }
    foreach ($cleanupOrderId in $cleanupOrderIds) {
      $photoRows = Invoke-TestMySql @('-N', '-B', '-D', 'emc_shoes_care', '-e', "SELECT o.storage_key,p.storage_name FROM orders o INNER JOIN order_photos p ON p.order_id=o.id WHERE o.id=$cleanupOrderId;")
      foreach ($photoRow in @($photoRows)) {
        if ($photoRow) {
          $parts = $photoRow -split "`t"
          if ($parts.Count -eq 2 -and $parts[0] -match '^[a-f0-9]{32}$' -and $parts[1] -match '^[a-f0-9]{32}\.(jpg|png|webp)$') {
            $storageRecords += [pscustomobject]@{ StorageKey = $parts[0]; StorageName = $parts[1] }
          }
        }
      }
      Invoke-TestMySql @('-D', 'emc_shoes_care', '-e', "DELETE FROM orders WHERE id=$cleanupOrderId;") | Out-Null
    }
    if ($testPackageId -gt 0) { Invoke-TestMySql @('-D', 'emc_shoes_care', '-e', "DELETE FROM packages WHERE id=$testPackageId;") | Out-Null }
    if ($customerId -gt 0) { Invoke-TestMySql @('-D', 'emc_shoes_care', '-e', "DELETE FROM auth_sessions WHERE customer_id=$customerId; DELETE FROM customers WHERE id=$customerId;") | Out-Null }
    if ($otherCustomerId -gt 0) { Invoke-TestMySql @('-D', 'emc_shoes_care', '-e', "DELETE FROM auth_sessions WHERE customer_id=$otherCustomerId; DELETE FROM customers WHERE id=$otherCustomerId;") | Out-Null }
  }
  foreach ($storageRecord in $storageRecords) {
    $root = [System.IO.Path]::GetFullPath((Join-Path (Split-Path -Parent $PSScriptRoot) 'storage\order-photos'))
    $directory = [System.IO.Path]::GetFullPath((Join-Path $root $storageRecord.StorageKey))
    $requiredPrefix = $root.TrimEnd([System.IO.Path]::DirectorySeparatorChar) + [System.IO.Path]::DirectorySeparatorChar
    if ($directory.StartsWith($requiredPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
      $file = Join-Path $directory $storageRecord.StorageName
      if ([System.IO.File]::Exists($file)) { [System.IO.File]::Delete($file) }
      if ([System.IO.Directory]::Exists($directory) -and [System.IO.Directory]::GetFileSystemEntries($directory).Count -eq 0) {
        [System.IO.Directory]::Delete($directory)
      }
    }
  }
}
