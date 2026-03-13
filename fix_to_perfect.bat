@echo off
chcp 65001 > nul
cd /d C:\xampp\htdocs\udruwisdom

echo ============================================================
echo   RESTORE - Bringing back the PERFECT VERSION (16ac485)
echo   - With phao2024 in sidebar
echo   - With Footer enabled
echo   - With Notifications system
echo ============================================================
echo.

echo [1/2] Restoring index.php and sidebar.php...
git checkout 16ac485 -- index.php includes/sidebar.php

echo [2/2] Cleaning up temporary version files...
rd /s /q versions 2>nul
del check_history.bat 2>nul
del extract_versions.bat 2>nul
del find_footer.bat 2>nul
del deep_find.bat 2>nul
del rollback_now.bat 2>nul
del get_git_log.bat 2>nul
del extract_history.bat 2>nul

echo.
echo ============================================================
echo   SUCCESS! The system has been restored to your
echo   preferred version. Please Refresh your browser.
echo ============================================================
pause
