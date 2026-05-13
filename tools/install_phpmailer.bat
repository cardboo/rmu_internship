@echo off
REM ====================================================================
REM  Install / refresh PHPMailer (stable 6.9.x) into lib/PHPMailer/.
REM
REM  Why a batch file?
REM    The PHPMailer 7.0.x line is pre-release and has shown TCP-
REM    connect quirks on Windows. 6.9.x is the stable line and works
REM    out of the box with our includes/email.php integration.
REM
REM  Run from the project root:
REM      cd C:\wamp642\www\rmu_internship
REM      tools\install_phpmailer.bat
REM ====================================================================

setlocal
set PMVER=6.9.3
set TARGET=lib\PHPMailer\PHPMailer-%PMVER%

echo.
echo === Installing PHPMailer v%PMVER% into %TARGET% ===
echo.

REM Wipe any previous PHPMailer-* folder under lib/PHPMailer/ to avoid
REM having two versions side by side (the auto-loader would pick the
REM first one alphabetically).
for /d %%D in (lib\PHPMailer\PHPMailer-*) do (
    echo Removing old vendor dir: %%D
    rmdir /S /Q "%%D"
)

REM Prefer git (you already have it) ...
where git >nul 2>&1
if %ERRORLEVEL% == 0 (
    echo Using git ...
    git clone --depth 1 --branch v%PMVER% https://github.com/PHPMailer/PHPMailer.git %TARGET%
    if %ERRORLEVEL% NEQ 0 goto fail
    REM Drop the .git folder; we just need the source.
    rmdir /S /Q "%TARGET%\.git"
    goto done
)

REM ... otherwise PowerShell.
echo git not found, falling back to PowerShell download ...
powershell -NoProfile -Command ^
  "Invoke-WebRequest -Uri https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v%PMVER%.zip -OutFile lib\PHPMailer\pm.zip; " ^
  "Expand-Archive -Force lib\PHPMailer\pm.zip lib\PHPMailer\; " ^
  "Remove-Item lib\PHPMailer\pm.zip"
if %ERRORLEVEL% NEQ 0 goto fail

:done
echo.
echo === PHPMailer v%PMVER% installed. Now run: ===
echo     php tools\test_email.php your.real.address@gmail.com
echo.
goto :eof

:fail
echo.
echo === Install FAILED. Check your internet connection or grab v%PMVER%
echo     manually from https://github.com/PHPMailer/PHPMailer/releases ===
exit /b 1
