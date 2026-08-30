[CmdletBinding()]
param(
    [string]$BrowserPath = $env:CHROME_BIN,
    [string]$TestHome = $env:XIUNO_TEST_HOME,
    [string]$PhpBinary = $env:XIUNO_PHP_BINARY
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path

if ([string]::IsNullOrWhiteSpace($PhpBinary)) {
    throw 'Pass -PhpBinary or set XIUNO_PHP_BINARY; the browser runner does not resolve php from PATH.'
}
if (-not (Test-Path -LiteralPath $PhpBinary -PathType Leaf)) {
    throw "PHP binary is not a file: $PhpBinary"
}
$PhpBinary = (Resolve-Path -LiteralPath $PhpBinary).Path

if ([string]::IsNullOrWhiteSpace($BrowserPath)) {
    $browserCandidates = @(
        'C:\Program Files\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
        'C:\Program Files\Microsoft\Edge\Application\msedge.exe'
    )
    $BrowserPath = $browserCandidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1
}
if ([string]::IsNullOrWhiteSpace($BrowserPath) -or -not (Test-Path -LiteralPath $BrowserPath -PathType Leaf)) {
    throw 'A Chromium browser is required. Set CHROME_BIN to chrome.exe or msedge.exe.'
}

if ([string]::IsNullOrWhiteSpace($TestHome)) {
    $TestHome = [System.IO.Path]::GetTempPath()
}
if (-not (Test-Path -LiteralPath $TestHome -PathType Container)) {
    New-Item -ItemType Directory -Path $TestHome -Force | Out-Null
}
$testHomeResolved = [System.IO.Path]::GetFullPath((Resolve-Path -LiteralPath $TestHome).Path).TrimEnd('\')
$fixtureRoot = Join-Path $testHomeResolved ('xiuno-bs4-browser-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $fixtureRoot | Out-Null
$fixtureResolved = [System.IO.Path]::GetFullPath((Resolve-Path -LiteralPath $fixtureRoot).Path)
$expectedPrefix = $testHomeResolved + [System.IO.Path]::DirectorySeparatorChar
if (-not $fixtureResolved.StartsWith($expectedPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'Refusing to use a browser fixture directory outside XIUNO_TEST_HOME.'
}

$serverProcess = $null
$browserProcess = $null
try {
    $port = Get-Random -Minimum 18000 -Maximum 19000
    $serverOut = Join-Path $fixtureRoot 'php-server.out.log'
    $serverError = Join-Path $fixtureRoot 'php-server.error.log'
    $serverProcess = Start-Process -FilePath $PhpBinary -ArgumentList @('-S', "127.0.0.1:$port", '-t', $repoRoot) -WorkingDirectory $repoRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput $serverOut -RedirectStandardError $serverError

    $fixtureUrl = "http://127.0.0.1:$port/bin/fixtures/bs4_compat_runtime.html"
    $ready = $false
    for ($attempt = 0; $attempt -lt 50; $attempt++) {
        if ($serverProcess.HasExited) { break }
        try {
            $response = Invoke-WebRequest -Uri $fixtureUrl -UseBasicParsing -TimeoutSec 2
            if ($response.StatusCode -eq 200) {
                $ready = $true
                break
            }
        } catch {
            Start-Sleep -Milliseconds 100
        }
    }
    if (-not $ready) {
        $serverDiagnostics = @()
        if (Test-Path -LiteralPath $serverOut) { $serverDiagnostics += Get-Content -Raw -LiteralPath $serverOut }
        if (Test-Path -LiteralPath $serverError) { $serverDiagnostics += Get-Content -Raw -LiteralPath $serverError }
        throw ('Fixture HTTP server did not become ready. ' + ($serverDiagnostics -join ' '))
    }

    foreach ($assetMode in @('source', 'min')) {
        $query = if ($assetMode -eq 'min') { '?assets=min' } else { '' }
        $resultFile = Join-Path $fixtureRoot "result-$assetMode.html"
        $browserLog = Join-Path $fixtureRoot "browser-$assetMode.log"
        $profileDir = Join-Path $fixtureRoot "profile-$assetMode"
        $cacheDir = Join-Path $fixtureRoot "cache-$assetMode"
        New-Item -ItemType Directory -Path $profileDir, $cacheDir | Out-Null
        $arguments = @(
            '--headless=new',
            '--no-sandbox',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--disable-background-networking',
            '--disable-breakpad',
            '--disable-crash-reporter',
            '--no-first-run',
            '--no-default-browser-check',
            "--user-data-dir=$profileDir",
            "--disk-cache-dir=$cacheDir",
            '--virtual-time-budget=30000',
            '--dump-dom',
            ($fixtureUrl + $query)
        )
        $browserProcess = Start-Process -FilePath $BrowserPath -ArgumentList $arguments -WindowStyle Hidden -PassThru -RedirectStandardOutput $resultFile -RedirectStandardError $browserLog
        if (-not $browserProcess.WaitForExit(45000)) {
            Stop-Process -Id $browserProcess.Id -Force -ErrorAction SilentlyContinue
            throw "Chromium compatibility fixture timed out for $assetMode assets."
        }
        # Start-Process can report HasExited while the redirected stream/ExitCode state has not yet
        # been refreshed on Windows. Complete the wait and refresh before classifying the run.
        $browserProcess.WaitForExit()
        $browserProcess.Refresh()
        $browserExitCode = $browserProcess.ExitCode
        # Some Chromium launchers detach into the profile process and leave ExitCode unavailable.
        # The dumped DOM below is the authoritative completion/result contract in that case.
        if ($null -ne $browserExitCode -and $browserExitCode -ne 0) {
            $diagnostic = if (Test-Path -LiteralPath $browserLog) { Get-Content -Raw -LiteralPath $browserLog } else { '' }
            $displayExitCode = if ($null -eq $browserExitCode) { '<unavailable>' } else { [string]$browserExitCode }
            throw "Chromium exited with code $displayExitCode for $assetMode assets. $diagnostic"
        }
        $result = Get-Content -Raw -LiteralPath $resultFile
        if ($result -notmatch 'data-complete="1"' -or $result -notmatch 'data-failed="0"') {
            $diagnostic = if (Test-Path -LiteralPath $browserLog) { Get-Content -Raw -LiteralPath $browserLog } else { '' }
            throw "Chromium compatibility fixture failed for $assetMode assets. $diagnostic`n$result"
        }
        $browserProcess = $null
    }

    Write-Output 'OK: Chromium BS4 compatibility behavior fixture passed for source and generated assets'
} finally {
    if ($browserProcess -and -not $browserProcess.HasExited) {
        Stop-Process -Id $browserProcess.Id -Force -ErrorAction SilentlyContinue
    }
    if ($serverProcess -and -not $serverProcess.HasExited) {
        Stop-Process -Id $serverProcess.Id -Force -ErrorAction SilentlyContinue
    }
    if (Test-Path -LiteralPath $fixtureResolved -PathType Container) {
        $finalFixture = [System.IO.Path]::GetFullPath((Resolve-Path -LiteralPath $fixtureResolved).Path)
        if (-not $finalFixture.StartsWith($expectedPrefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            throw 'Refusing to remove a browser fixture directory outside XIUNO_TEST_HOME.'
        }
        # Chromium can let a profile child outlive the launcher briefly (notably Edge's dictionary
        # worker). Stop only processes whose command line contains this unique fixture path, then
        # retry the verified cleanup for a bounded period instead of reporting a false test failure.
        try {
            Get-CimInstance Win32_Process -ErrorAction Stop |
                Where-Object { $_.ProcessId -ne $PID -and $_.CommandLine -and $_.CommandLine.IndexOf($finalFixture, [System.StringComparison]::OrdinalIgnoreCase) -ge 0 } |
                ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
        } catch {
            # Process discovery is best effort; the bounded filesystem retry below remains authoritative.
        }
        $cleanupComplete = $false
        for ($cleanupAttempt = 0; $cleanupAttempt -lt 50; $cleanupAttempt++) {
            try {
                Remove-Item -LiteralPath $finalFixture -Recurse -Force -ErrorAction Stop
                $cleanupComplete = $true
                break
            } catch {
                if ($cleanupAttempt -ge 49) { throw }
                Start-Sleep -Milliseconds 100
            }
        }
        if (-not $cleanupComplete) {
            throw 'Unable to remove the Chromium compatibility fixture directory.'
        }
    }
}
