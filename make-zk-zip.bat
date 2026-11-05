@echo off
setlocal

:: ============================================================
:: make-zk-zip.bat
:: Creates a CLEAN zip of the ZKs-RMS project for uploading to Claude.
:: - Never modifies, deletes, or moves anything in the original folder
:: - Excludes ONLY the root vendor (public\vendor is kept), node_modules, .git, logs, .env,
::   SQLite files, uploaded payment proofs (storage\app), the framework cache
::   (storage\framework) and the phpunit cache
:: - Leaves out bootstrap\cache\*.php (a stale routes file there breaks the tests)
:: - Puts back EMPTY storage folders (with a .gitkeep) so the AI can run php artisan test from the zip
- Saves the result to your Downloads folder as zk-clean.zip
:: ============================================================

:: trailing "." avoids the backslash-quote bug with robocopy
set "SRC=%~dp0."
set "WORK=%TEMP%\zk_clean_build_%RANDOM%"
set "OUT=%USERPROFILE%\Downloads\zk-clean.zip"

echo Reading project from: %SRC%
echo (original folder will NOT be changed)
echo.

if exist "%WORK%" rmdir /s /q "%WORK%"

robocopy "%SRC%" "%WORK%" /E /XD "%~dp0vendor" "%~dp0node_modules" "%~dp0.git" "%~dp0storage\app" "%~dp0storage\framework" logs .idea .vscode /XF *.log .env *.sqlite .phpunit.result.cache make-zk-zip.bat zk-clean.zip zk-fixes.zip /NFL /NDL /NJH /NJS /NP
if errorlevel 8 (
  echo.
  echo FAILED: robocopy could not copy the files. Send the error above.
  pause
  exit /b 1
)

:: stale cached routes/config break php artisan test, so never ship them
if exist "%WORK%\bootstrap\cache" del /q "%WORK%\bootstrap\cache\*.php" 2>nul

:: storage\app and storage\framework are excluded above, but Laravel needs the empty folders to run
:: (without storage\framework\views every page fails with "Please provide a valid cache path")
for %%D in (storage\framework\views storage\framework\cache storage\framework\sessions storage\logs storage\app\private storage\app\public) do (
  if not exist "%WORK%\%%D" mkdir "%WORK%\%%D"
  type nul > "%WORK%\%%D\.gitkeep"
)

powershell -NoProfile -Command "if (Test-Path '%OUT%') { Remove-Item '%OUT%' }; Compress-Archive -Path '%WORK%\*' -DestinationPath '%OUT%' -Force; if (Test-Path '%OUT%') { $kb = [math]::Round((Get-Item '%OUT%').Length / 1KB); Write-Host ('SUCCESS: %OUT% (' + $kb + ' KB)') } else { Write-Host 'FAILED: zip was not created' }"

rmdir /s /q "%WORK%"

echo.
echo (If it says SUCCESS above, your zip is in your Downloads folder.)
echo (If it says FAILED, copy the error text above and send it back.)
echo.
pause
