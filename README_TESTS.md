# UniLearn Job Offer Workflow Tests

This directory contains Python-based end-to-end tests for the UniLearn job offer workflow that simulates:

1. **Partner Login** - Partner logs in with provided credentials
2. **Job Offer Creation** - Partner creates a new job offer
3. **Admin Login** - Admin logs in with provided credentials  
4. **Job Offer Approval** - Admin approves the created job offer

## Test Files

### Main Test Scripts

- **`test_job_offer_workflow.py`** - Standalone comprehensive test script
- **`test_job_offer_workflow_pytest.py`** - Pytest-compatible test suite
- **`run_tests.py`** - Test runner script that handles setup and execution

### Configuration Files

- **`test_requirements.txt`** - Python dependencies
- **`README_TESTS.md`** - This documentation

## Prerequisites

1. **UniLearn Server Running**: Ensure your UniLearn application is running at `http://127.0.0.1:8000`
   ```bash
   cd /path/to/unilearn
   symfony server:start
   ```

2. **Python 3.7+**: Make sure you have Python installed

3. **Test Accounts**: The tests use these credentials (from your browser password manager):
   - **Partner**: `amri.dhia21@gmail.com` / `C*RRcdS73glX`
   - **Admin**: `admin@unilearn.com` / `admin123`

## Quick Start

### Option 1: Use the Test Runner (Recommended)
```bash
# Run the automated test runner
python run_tests.py
```

The test runner will:
- Install required dependencies automatically
- Check if your server is running
- Run both test scripts
- Provide a summary of results

### Option 2: Manual Setup and Execution

1. **Install Dependencies**:
   ```bash
   pip install -r test_requirements.txt
   ```

2. **Run Standalone Test**:
   ```bash
   python test_job_offer_workflow.py
   ```

3. **Run Pytest Tests**:
   ```bash
   pytest test_job_offer_workflow_pytest.py -v
   # Or with verbose output:
   pytest test_job_offer_workflow_pytest.py -v -s
   ```

## Test Details

### What the Tests Do

1. **Authentication Testing**:
   - Extracts CSRF tokens from login forms
   - Submits proper login credentials
   - Manages session cookies
   - Verifies successful authentication

2. **Job Offer Creation**:
   - Navigates to partner job offer creation page
   - Extracts form structure and CSRF tokens
   - Populates job offer form with test data:
     - Title: `Test Job Offer - [timestamp]`
     - Type: `FULL_TIME`
     - Location: `Tunis, Tunisia`
     - Description: Test description
   - Submits the form and verifies creation

3. **Admin Approval**:
   - Logs in as admin
   - Navigates to admin job offer management
   - Locates the created job offer
   - Extracts approval form and CSRF token
   - Submits approval and verifies success

### Test Data

The tests create job offers with unique timestamps to avoid conflicts:
```python
test_job_offer = {
    'title': f'Test Job Offer - {datetime.now().strftime("%Y%m%d_%H%M%S")}',
    'type': 'FULL_TIME',
    'location': 'Tunis, Tunisia',
    'description': 'This is a test job offer created by automated testing script.',
    'requirements': 'Python, Testing, Automation',
    'salary': '2000',
    'deadline': '2026-12-31T23:59'
}
```

## Test Output Examples

### Successful Test Run
```
🚀 Starting UniLearn Job Offer Workflow Test
============================================================
🔐 Logging in Partner: amri.dhia21@gmail.com
✅ Partner login successful
📝 Creating job offer as partner...
📤 Submitting job offer with data: {...}
✅ Job offer created successfully
📋 Found created job offer ID: 123
🚪 User logged out
🔐 Logging in Admin: admin@unilearn.com
✅ Admin login successful
✅ Attempting to approve job offer as admin...
🎯 Found job offer to approve: ID 123
✅ Job offer approved successfully
============================================================
🎉 All workflow tests completed successfully!

✅ TEST PASSED: Job offer workflow completed successfully
```

### Failed Test Run
```
🔐 Logging in Partner: amri.dhia21@gmail.com
❌ Partner login failed: 403
Response content: <html>...invalid credentials...

❌ TEST FAILED: Job offer workflow encountered errors
```

## Troubleshooting

### Common Issues

1. **Server Not Running**
   ```
   ❌ Server is not accessible: Connection refused
   💡 Please start the UniLearn server with: symfony server:start
   ```
   **Solution**: Start your Symfony server first

2. **Invalid Credentials**
   ```
   ❌ Partner login failed: 403
   ```
   **Solution**: Verify credentials are correct in `test_config`

3. **CSRF Token Issues**
   ```
   ❌ Could not extract CSRF token from login page
   ```
   **Solution**: Check if the application structure has changed

4. **Form Field Mismatch**
   ```
   ❌ Could not find job offer form
   ```
   **Solution**: The form structure may have changed - update field mappings

### Debugging Tips

1. **Enable Verbose Output**:
   ```bash
   pytest test_job_offer_workflow_pytest.py -v -s
   ```

2. **Check Server Logs**:
   ```bash
   tail -f var/log/dev.log
   ```

3. **Manual Browser Test**: Verify the workflow manually in your browser first

4. **Network Debugging**: Use tools like `curl` to test API endpoints manually

## Customization

### Changing Test Data
Modify the test data in the script:
```python
self.test_job_offer = {
    'title': 'Your Custom Title',
    'type': 'INTERNSHIP',  # or FULL_TIME, PART_TIME, etc.
    'location': 'Your Location',
    'description': 'Your description',
    # ... other fields
}
```

### Adding New Test Cases
For pytest, add new test methods to the `TestJobOfferWorkflow` class:
```python
def test_job_offer_rejection(self, test_config, test_session):
    """Test job offer rejection by admin"""
    # Your test logic here
    pass
```

### Changing Credentials
Update the credentials in the test configuration:
```python
self.partner_credentials = {
    'email': 'your-partner@email.com',
    'password': 'your-password'
}
```

## Integration with CI/CD

These tests can be integrated into your CI/CD pipeline:

```yaml
# Example GitHub Actions workflow
test_job_offer_workflow:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v2
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
    - name: Install dependencies
      run: composer install
    - name: Start Symfony server
      run: |
        symfony server:start -d
        sleep 10
    - name: Run E2E tests
      run: python run_tests.py
```

## Security Notes

⚠️ **Important Security Considerations**:

- These tests use real credentials and create actual data in your application
- Run tests against a development/testing database, never production
- The test credentials are hardcoded - in production, use environment variables:
  ```python
  import os
  partner_email = os.getenv('TEST_PARTNER_EMAIL', 'default@email.com')
  ```
- Consider implementing test data cleanup mechanisms

## Support

If you encounter issues:

1. Check that your UniLearn application is working correctly in the browser
2. Verify all form field names and routing match your current application version
3. Ensure the test credentials have the necessary permissions
4. Check server logs for any application-level errors

The tests are designed to be robust but may need updates if the application structure changes significantly.