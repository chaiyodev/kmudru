@echo off
chcp 65001 > nul
cd /d C:\xampp\htdocs\udruwisdom

echo [1/2] Restoring mission notifications files...
git checkout 16ac485 -- includes/notifications.php
git checkout 16ac485 -- notifications.php

echo [2/2] Checking if file exists now...
if exist includes\notifications.php (
    echo - includes/notifications.php RESTORED
) else (
    echo - includes/notifications.php NOT FOUND in commit 16ac485
)

pause
