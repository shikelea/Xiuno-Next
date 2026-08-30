@echo off
setlocal EnableExtensions

if defined XIUNO_PHP_BINARY goto explicit_php
where php >nul 2>nul || (
  echo FAIL: PHP is required. Set XIUNO_PHP_BINARY to the PHP CLI executable. 1>&2
  exit /b 2
)
php "%~dp0benchmark.php" %*
exit /b %ERRORLEVEL%

:explicit_php
if not exist "%XIUNO_PHP_BINARY%" (
  echo FAIL: XIUNO_PHP_BINARY is not a file: %XIUNO_PHP_BINARY% 1>&2
  exit /b 2
)
"%XIUNO_PHP_BINARY%" "%~dp0benchmark.php" %*
exit /b %ERRORLEVEL%
