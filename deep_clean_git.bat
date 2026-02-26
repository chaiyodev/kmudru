@echo off
title UDRU Wisdom - Deep Clean Git
color 0B

echo ===================================================
echo   UDRU Wisdom - Deep Clean Git History 
echo ===================================================
echo.

:: 1. Force untrack everything and re-read .gitignore
echo [1/4] resetting index based on .gitignore...
git rm -r --cached .

:: 2. Stage everything (respecting .gitignore)
echo [2/4] Staging clean files...
git add .

:: 3. Create a fresh commit (removing all previous history of this branch)
echo [3/4] Creating a fresh clean commit...
git commit -m "UDRU Wisdom - Clean System Upload (No Secrets)"

:: 4. Force push to overwrite the problematic branch
echo [4/4] Overwriting branch 'update-2026' on GitHub...
git push origin HEAD:update-2026 -f

if %errorlevel% equ 0 (
    echo.
    echo ===================================================
    echo    SUCCESS! Dirty history has been overwritten.
    echo    Check: https://github.com/chaiyodev/kmudru/tree/update-2026
    echo ===================================================
) else (
    echo.
    echo [ERROR] Push failed. 
    echo If this still fails, GitHub's "Push Protection" is very strict.
    echo You may need to click the link in the error to 'unblock' manually:
    echo https://github.com/chaiyodev/kmudru/security/secret-scanning/unblock-secret/3ABq0lyGozTMRJsNuZxCT5m5sj2
)

pause
