@echo off
title UDRU Wisdom - Fix Git Secrets
color 0E

echo ===================================================
echo   UDRU Wisdom - Fixing Git Secret Tracking 
echo ===================================================
echo.

:: 1. Untrack sensitive files
echo [1/4] Untracking sensitive files (keeping local files)...
git rm --cached includes/db.php 2>nul
git rm --cached includes/config.php 2>nul
git rm --cached includes/google_config.php 2>nul

:: 2. Re-stage everything else
echo [2/4] Staging changes...
git add .

:: 3. Amend the commit (fixing the history)
echo [3/4] Amending previous commit...
git commit --amend --no-edit

:: 4. Force push
echo [4/4] Force pushing to GitHub...
git push -f origin update-2026

if %errorlevel% equ 0 (
    echo.
    echo ===================================================
    echo    SUCCESS! Secrets removed and code pushed.
    echo    Check: https://github.com/chaiyodev/kmudru/tree/update-2026
    echo ===================================================
) else (
    echo.
    echo [ERROR] Push failed. 
    echo If GitHub still blocks you, you might need to 'allow' the secret
    echo in GitHub settings or check your connection.
)

pause
