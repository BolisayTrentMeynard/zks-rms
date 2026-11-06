@echo off
setlocal

:: ============================================================
:: tools\make-zk-zip.bat      (OPTIONAL helper, safe to ignore)
::
:: Makes a small, CLEAN zip of the project that you can upload to an AI chat,
:: so the AI sees only the code and not the heavy or private parts.
::
:: - Never changes, deletes or moves anything in the project folder.
:: - Works from wherever you cloned the project (it finds the project root
::   from its own location, so no paths to edit).
:: - Leaves OUT: vendor (project root only), node_modules, .git, logs, every
::   .env file (your real .env holds your own API key), SQLite files, uploaded
::   payment proofs and the framework cache (storage\app, storage\framework),
::   public\build and public\images (generated or binary, no use to an AI),
::   composer.lock, package-lock.json, auth.json, bootstrap\cache\*.php.
::   .env.example IS included.
:: - Puts back EMPTY storage folders (with a .gitkeep) so the folder
::   structure is complete.
:: - Saves the result to your Downloads folder as zk-clean.zip
:: - Before zipping it checks that no .env file slipped in; if one did, it
::   stops and makes no zip.
:: ============================================================

:: the project root is the folder above this tools folder
for %%I in ("%~dp0..") do set "ROOT=%%~fI"

if not exist "%ROOT%\artisan" (
  echo.
  echo FAILED: this script must stay inside the project's tools folder.
  echo Could not find the project root at: %ROOT%
  pause
  exit /b 1
)

:: trailing "." avoids the backslash-quote bug with robocopy
set "SRC=%ROOT%\."
set "WORK=%TEMP%\zk_clean_build_%RANDOM%"
set "OUT=%USERPROFILE%\Downloads\zk-clean.zip"

echo Reading project from: %ROOT%
echo (original folder will NOT be changed)
echo.

if exist "%WORK%" rmdir /s /q "%WORK%"

:: /XJ skips junction points (for example public\storage after storage:link) so nothing is copied twice or loops
robocopy "%SRC%" "%WORK%" /E /XJ /XD "%ROOT%\vendor" "%ROOT%\node_modules" "%ROOT%\.git" "%ROOT%\storage\app" "%ROOT%\storage\framework" "%ROOT%\public\build" "%ROOT%\public\images" logs .idea .vscode .phpunit.cache /XF *.log .env .env.* *.sqlite *.sqlite-journal .phpunit.result.cache auth.json composer.lock package-lock.json make-zk-zip.bat zk-clean.zip zk-fixes.zip /NFL /NDL /NJH /NJS /NP
if errorlevel 8 (
  echo.
  echo FAILED: robocopy could not copy the files. Send the error above.
  pause
  exit /b 1
)

:: ".env.*" above also skipped .env.example, which is safe and useful, so put just that one back
if exist "%ROOT%\.env.example" copy /y "%ROOT%\.env.example" "%WORK%\" >nul

:: stale cached routes/config break php artisan test, so never ship them
if exist "%WORK%\bootstrap\cache" del /q "%WORK%\bootstrap\cache\*.php" 2>nul

:: storage\app and storage\framework are excluded above, but Laravel needs the empty folders to run
:: (without storage\framework\views every page fails with "Please provide a valid cache path")
for %%D in (storage\framework\views storage\framework\cache storage\framework\sessions storage\logs storage\app\private storage\app\public) do (
  if not exist "%WORK%\%%D" mkdir "%WORK%\%%D"
  type nul > "%WORK%\%%D\.gitkeep"
)

:: safety check: no .env file (other than .env.example) may be inside the copy
set "LEAK="
for /r "%WORK%" %%F in (.env .env.*) do (
  if exist "%%F" if /i not "%%~nxF"==".env.example" set "LEAK=1"
)
if defined LEAK (
  echo.
  echo FAILED: a .env file ended up in the copy. NO zip was made.
  echo Do not share it. Tell the project maintainer.
  rmdir /s /q "%WORK%"
  pause
  exit /b 1
)
echo Checked: no .env file in the copy.

:: the paths go in through environment variables so spaces or apostrophes in a user name cannot break the command
set "ZK_WORK=%WORK%"
set "ZK_OUT=%OUT%"
powershell -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; Add-Type -AssemblyName System.IO.Compression.FileSystem; if (Test-Path -LiteralPath $env:ZK_OUT) { Remove-Item -LiteralPath $env:ZK_OUT -Force }; [System.IO.Compression.ZipFile]::CreateFromDirectory($env:ZK_WORK, $env:ZK_OUT, [System.IO.Compression.CompressionLevel]::Optimal, $false); if (Test-Path -LiteralPath $env:ZK_OUT) { $kb = [math]::Round((Get-Item -LiteralPath $env:ZK_OUT).Length / 1KB); Write-Host ('SUCCESS: ' + $env:ZK_OUT + ' (' + $kb + ' KB)') } else { Write-Host 'FAILED: zip was not created' }"

rmdir /s /q "%WORK%"

echo.
echo (If it says SUCCESS above, your zip is in your Downloads folder.)
echo (If it says FAILED, copy the error text above and send it back.)
echo.
pause
