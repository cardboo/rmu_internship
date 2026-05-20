@echo off
REM Run the daily reminder dispatcher. Schedule this from
REM Windows Task Scheduler:
REM
REM   Task Scheduler -> Create Basic Task
REM     Name:    RMU Internship Reminders
REM     Trigger: Daily, 07:00
REM     Action:  Start a program
REM       Program/script: C:\wamp64\www\rmu_internship\tools\send_reminders.bat
REM       Start in:       C:\wamp64\www\rmu_internship
REM
REM Adjust PHP_BIN below if your WAMP install puts php.exe somewhere
REM else (e.g. C:\wamp642\bin\php\php7.4.33\php.exe).

SET PHP_BIN=C:\wamp64\bin\php\php7.4.33\php.exe
SET PROJECT_DIR=%~dp0..
SET LOG_DIR=%PROJECT_DIR%\logs

IF NOT EXIST "%LOG_DIR%" mkdir "%LOG_DIR%"

REM Append-only log so you can audit what ran without overwriting history.
"%PHP_BIN%" "%PROJECT_DIR%\tools\send_reminders.php" >> "%LOG_DIR%\reminders.log" 2>&1

EXIT /B %ERRORLEVEL%
