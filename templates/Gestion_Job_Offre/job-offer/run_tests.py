#!/usr/bin/env python3
"""
Setup and run script for UniLearn Job Offer Workflow Tests

This script will:
1. Install required dependencies
2. Check if the UniLearn server is running
3. Run the job offer workflow tests

Usage:
    python run_tests.py
"""

import subprocess
import sys
import requests
import json
from pathlib import Path

def install_dependencies():
    """Install required Python packages"""
    print("📦 Installing test dependencies...")
    
    try:
        subprocess.check_call([
            sys.executable, "-m", "pip", "install", 
            "-r", "test_requirements.txt"
        ])
        print("✅ Dependencies installed successfully")
        return True
    except subprocess.CalledProcessError as e:
        print(f"❌ Failed to install dependencies: {e}")
        return False

def check_server_status(base_url="http://127.0.0.1:8000"):
    """Check if UniLearn server is running"""
    print(f"🌐 Checking server status at {base_url}...")
    
    try:
        response = requests.get(f"{base_url}/login", timeout=5)
        if response.status_code == 200:
            print("✅ UniLearn server is running")
            return True
        else:
            print(f"⚠️ Server responded with status code: {response.status_code}")
            return False
    except requests.exceptions.RequestException as e:
        print(f"❌ Server is not accessible: {e}")
        print("💡 Please start the UniLearn server with: symfony server:start")
        return False

def run_standalone_test():
    """Run the standalone test script"""
    print("🧪 Running standalone job offer workflow test...")
    
    try:
        result = subprocess.run([
            sys.executable, "test_job_offer_workflow.py"
        ], capture_output=True, text=True)
        
        print("STDOUT:", result.stdout)
        if result.stderr:
            print("STDERR:", result.stderr)
        
        if result.returncode == 0:
            print("✅ Standalone test passed")
            return True
        else:
            print(f"❌ Standalone test failed with exit code: {result.returncode}")
            return False
    except Exception as e:
        print(f"💥 Error running standalone test: {e}")
        return False

def run_pytest_tests():
    """Run pytest tests"""
    print("🧪 Running pytest workflow tests...")
    
    try:
        result = subprocess.run([
            sys.executable, "-m", "pytest", 
            "test_job_offer_workflow_pytest.py", 
            "-v", "-s"
        ], capture_output=True, text=True)
        
        print("STDOUT:", result.stdout)
        if result.stderr:
            print("STDERR:", result.stderr)
        
        if result.returncode == 0:
            print("✅ Pytest tests passed")
            return True
        else:
            print(f"❌ Pytest tests failed with exit code: {result.returncode}")
            return False
    except Exception as e:
        print(f"💥 Error running pytest tests: {e}")
        return False

def main():
    """Main function"""
    print("🚀 UniLearn Job Offer Workflow Test Runner")
    print("=" * 60)
    
    # Check if requirements file exists
    if not Path("test_requirements.txt").exists():
        print("❌ test_requirements.txt not found")
        return False
    
    # Install dependencies
    if not install_dependencies():
        return False
    
    # Check server status
    if not check_server_status():
        print("💡 Please ensure UniLearn server is running before running tests")
        return False
    
    print("\n" + "=" * 60)
    print("🎮 Running Tests")
    print("=" * 60)
    
    # Run tests
    standalone_success = run_standalone_test()
    pytest_success = run_pytest_tests()
    
    print("\n" + "=" * 60)
    print("📊 Test Results Summary")
    print("=" * 60)
    
    if standalone_success:
        print("✅ Standalone test: PASSED")
    else:
        print("❌ Standalone test: FAILED")
    
    if pytest_success:
        print("✅ Pytest tests: PASSED")
    else:
        print("❌ Pytest tests: FAILED")
    
    overall_success = standalone_success or pytest_success
    
    if overall_success:
        print("\n🎉 At least one test suite passed!")
        return True
    else:
        print("\n💥 All tests failed!")
        return False

def quick_server_check():
    """Quick function to just check if server is running"""
    try:
        response = requests.get("http://127.0.0.1:8000/login", timeout=3)
        return response.status_code == 200
    except:
        return False

if __name__ == "__main__":
    try:
        success = main()
        
        if success:
            print("\n✨ Test execution completed successfully")
            sys.exit(0)
        else:
            print("\n🚨 Test execution failed")
            sys.exit(1)
            
    except KeyboardInterrupt:
        print("\n🛑 Test execution interrupted by user")
        sys.exit(1)
    except Exception as e:
        print(f"\n💥 Test execution crashed: {e}")
        sys.exit(1)