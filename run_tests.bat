@echo off
echo UniLearn Job Offer Workflow Test Runner
echo ========================================

REM Check if Python is installed
python --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Python is not installed or not in PATH
    echo Please install Python 3.7+ and try again
    pause
    exit /b 1
)

REM Check if the server is running
echo Checking if UniLearn server is running...
python -c "import requests; exit(0 if requests.get('http://127.0.0.1:8000/login', timeout=3).status_code == 200 else 1)" >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ERROR: UniLearn server is not running at http://127.0.0.1:8000
    echo.
    echo Please start the server first:
    echo   symfony server:start
    echo.
    echo Then run this script again.
    pause
    exit /b 1
)

echo Server is running - starting tests...
echo.

REM Run the test runner
python run_tests.py

echo.
echo Test execution completed. Check output above for results.
pause