@echo off
cd /d C:\xampp\htdocs\udruwisdom
echo [1/4] Creating new branch: update-2026...
git checkout -b update-2026
echo [2/4] Adding files...
git add .
echo [3/4] Committing changes...
git commit -m "Fix database migration, CoP stats, attachment display, and mobile balance (v2-update)"
echo [4/4] Pushing to GitHub (New Branch)...
git push origin update-2026
echo.
echo ============================================================
echo 🎉 Upload to NEW BRANCH complete!
echo Your original files on 'main' are safe.
echo You can see the new branch at: https://github.com/chaiyodev/kmudru/tree/update-2026
echo ============================================================
pause
