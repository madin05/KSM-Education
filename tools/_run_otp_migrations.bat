@echo off
REM Backup DB lalu terapkan migrasi 009 & 010 (idempoten) di DB lokal.
set MYSQL=C:\xampp\mysql\bin\mysql.exe
set MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe
set DB=journal_system2
set OUT=c:\xampp\htdocs\ksmedu\tools\_backup_before_otp.sql

echo [1/3] Backup ke %OUT%
"%MYSQLDUMP%" -u root --routines --events --single-transaction %DB% > "%OUT%"
if errorlevel 1 (echo BACKUP FAILED & exit /b 1)
for %%A in ("%OUT%") do echo     size=%%~zA bytes

echo [2/3] Apply 009_email_otp_verification.sql
"%MYSQL%" -u root %DB% < "c:\xampp\htdocs\ksmedu\database\migrations\009_email_otp_verification.sql"
if errorlevel 1 (echo 009 FAILED & exit /b 1)

echo [3/3] Apply 010_email_otp_ip_rate_limit.sql
"%MYSQL%" -u root %DB% < "c:\xampp\htdocs\ksmedu\database\migrations\010_email_otp_ip_rate_limit.sql"
if errorlevel 1 (echo 010 FAILED & exit /b 1)

echo DONE
