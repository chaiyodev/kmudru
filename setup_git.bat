@echo off
title UDRU Wisdom - Git Setup & Push
color 0A

echo ===================================================
echo      UDRU Wisdom - Automatic Git Setup Tool
echo ===================================================
echo.

:: 1. Check if Git is installed
where git >nul 2>nul
if %errorlevel% neq 0 (
    echo [ERROR] Git is not installed or not in your PATH.
    echo Please install Git from https://git-scm.com/download/win
    echo and try again.
    pause
    exit
)

:: 2. Initialize Git
echo [1/5] Initializing Git repository...
if not exist .git (
    git init
    echo    - Repository initialized.
) else (
    echo    - Git repository already exists.
)

:: 3. Add Files
echo.
echo [2/5] Adding all files to staging...
git add .

:: 4. Commit
echo.
echo [3/5] Committing files...
git commit -m "Complete UDRU Wisdom System (Phase 1-5)"

:: 5. Branch
git branch -M main

:: 6. Remote Setup
echo.
echo ===================================================
echo [4/5] Remote Repository Setup
echo.
echo Please go to https://github.com/new and create a new repository.
echo Copy the "HTTPS" URL (e.g., https://github.com/username/repo.git)
echo.
set /p remote_url="Paste your GitHub Repository URL here: "

git remote add origin %remote_url% 2>nul
if %errorlevel% neq 0 (
    echo    - Remote 'origin' already exists. Updating URL...
    git remote set-url origin %remote_url%
)

:: 7. Push
echo.
echo [5/5] Pushing to GitHub...
echo.
git push -u origin main

if %errorlevel% equ 0 (
    echo.
    echo ===================================================
    echo    SUCCESS! Your code is now on GitHub.
    echo ===================================================
) else (
    echo.
    echo [ERROR] Push failed. Please check your URL or internet connection.
)

pause
