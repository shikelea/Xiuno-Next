[CmdletBinding()]
param(
    [string]$BashPath = $env:XIUNO_BASH_BINARY,
    [string]$WslDistribution = $env:XIUNO_DOCKER_WSL_DISTRO
)

$ErrorActionPreference = 'Stop'
$scriptPath = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot 'check_docker_http_smoke.sh')).Path

if (-not [string]::IsNullOrWhiteSpace($BashPath)) {
    if (-not (Test-Path -LiteralPath $BashPath -PathType Leaf)) {
        Write-Error "XIUNO_BASH_BINARY is not a file: $BashPath"
        exit 2
    }
    $resolvedBash = (Resolve-Path -LiteralPath $BashPath).Path
    & $resolvedBash $scriptPath
    exit $LASTEXITCODE
}

$wsl = Get-Command 'wsl.exe' -ErrorAction SilentlyContinue
if ($null -eq $wsl) {
    Write-Output 'SKIP: Docker HTTP smoke on Windows requires WSL, or an explicit XIUNO_BASH_BINARY.'
    exit 0
}

$prefix = @()
if (-not [string]::IsNullOrWhiteSpace($WslDistribution)) {
    $prefix += @('--distribution', $WslDistribution)
}
$converted = & $wsl.Source @prefix -- wslpath -a -u $scriptPath
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace(($converted | Select-Object -First 1))) {
    Write-Error 'Unable to translate the Docker smoke script path into the selected WSL environment.'
    exit 2
}
$wslScript = [string]($converted | Select-Object -First 1)
& $wsl.Source @prefix -- bash $wslScript
exit $LASTEXITCODE
