#!/usr/bin/env python3
"""
End-to-End Test for Job Offer Workflow in UniLearn

This test simulates the complete workflow:
1. Partner logs in and creates a job offer
2. Admin logs in and approves the job offer

Requirements:
    pip install requests beautifulsoup4 pytest

Usage:
    python test_job_offer_workflow.py
"""

import requests
import re
import json
from typing import Dict, Optional, Tuple
from bs4 import BeautifulSoup
import time
from datetime import datetime

class UniLearnTester:
    """Test class for UniLearn job offer workflow"""
    
    def __init__(self, base_url: str = "http://127.0.0.1:8000"):
        self.base_url = base_url.rstrip('/')
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        })
        
        # Test credentials from user attachment
        self.partner_credentials = {
            'email': 'amri.dhia21@gmail.com',
            'password': 'C*RRcdS73glX'
        }
        
        self.admin_credentials = {
            'email': 'admin@unilearn.com',
            'password': 'admin123'
        }
        
        # Test data for job offer creation
        self.test_job_offer = {
            'title': f'Test Job Offer - {datetime.now().strftime("%Y%m%d_%H%M%S")}',
            'type': 'FULL_TIME',
            'location': 'Tunis, Tunisia',
            'description': 'This is a test job offer created by automated testing script.',
            'requirements': 'Python, Testing, Automation',
            'salary': '2000',
            'deadline': '2026-12-31T23:59'
        }
        
        self.created_job_offer_id = None

    def extract_csrf_token(self, html_content: str, form_name: str = None) -> Optional[str]:
        """Extract CSRF token from HTML content"""
        soup = BeautifulSoup(html_content, 'html.parser')
        
        # Look for CSRF token in hidden input field
        csrf_input = soup.find('input', {'name': '_csrf_token'})
        if csrf_input:
            return csrf_input.get('value')
            
        # Fallback: look for _token field
        csrf_input = soup.find('input', {'name': '_token'})
        if csrf_input:
            return csrf_input.get('value')
            
        # Fallback: look for token in form with specific name
        if form_name:
            form = soup.find('form', {'name': form_name})
            if form:
                csrf_input = form.find('input', {'name': '_csrf_token'})
                if csrf_input:
                    return csrf_input.get('value')
        
        return None

    def login_user(self, email: str, password: str, user_type: str) -> bool:
        """Login a user and return success status"""
        print(f"🔐 Logging in {user_type}: {email}")
        
        # Get login page to retrieve CSRF token
        login_url = f"{self.base_url}/login"
        response = self.session.get(login_url)
        
        if response.status_code != 200:
            print(f"❌ Failed to access login page: {response.status_code}")
            return False
        
        # Extract CSRF token
        csrf_token = self.extract_csrf_token(response.text)
        if not csrf_token:
            print("❌ Could not extract CSRF token from login page")
            return False
        
        # Prepare login data
        login_data = {
            '_username': email,
            '_password': password,
            '_csrf_token': csrf_token
        }
        
        # Submit login form
        response = self.session.post(login_url, data=login_data, allow_redirects=False)
        
        # Check if login was successful (should redirect)
        if response.status_code in [302, 301]:
            print(f"✅ {user_type} login successful")
            return True
        else:
            print(f"❌ {user_type} login failed: {response.status_code}")
            print(f"Response content: {response.text[:500]}")
            return False

    def logout_user(self):
        """Logout the current user"""
        logout_url = f"{self.base_url}/logout"
        response = self.session.get(logout_url)
        print("🚪 User logged out")

    def create_job_offer_as_partner(self) -> bool:
        """Create a job offer as partner"""
        print("📝 Creating job offer as partner...")
        
        # Access job offer creation page
        new_offer_url = f"{self.base_url}/partner/job-offers/new"
        response = self.session.get(new_offer_url)
        
        if response.status_code != 200:
            print(f"❌ Failed to access job offer creation page: {response.status_code}")
            return False
        
        # Extract CSRF token from form
        csrf_token = self.extract_csrf_token(response.text)
        if not csrf_token:
            print("❌ Could not extract CSRF token from job offer form")
            return False
        
        # Parse form to get proper field names
        soup = BeautifulSoup(response.text, 'html.parser')
        form = soup.find('form')
        
        if not form:
            print("❌ Could not find job offer form")
            return False
        
        # Prepare form data (field names might be prefixed)
        form_data = {
            '_token': csrf_token
        }
        
        # Try different possible field name formats
        field_mappings = [
            ('title', 'job_offer_form_type[title]', 'job_offer[title]'),
            ('type', 'job_offer_form_type[type]', 'job_offer[type]'),
            ('location', 'job_offer_form_type[location]', 'job_offer[location]'),
            ('description', 'job_offer_form_type[description]', 'job_offer[description]'),
            ('requirements', 'job_offer_form_type[requirements]', 'job_offer[requirements]'),
            ('salary', 'job_offer_form_type[salary]', 'job_offer[salary]'),
            ('deadline', 'job_offer_form_type[deadline]', 'job_offer[deadline]')
        ]
        
        # Find the actual field names in the form
        for field_key, *possible_names in field_mappings:
            field_found = False
            for name in possible_names:
                if soup.find('input', {'name': name}) or soup.find('textarea', {'name': name}) or soup.find('select', {'name': name}):
                    form_data[name] = self.test_job_offer[field_key]
                    field_found = True
                    break
            
            if not field_found:
                # Try to find by partial name match
                for input_elem in soup.find_all(['input', 'textarea', 'select']):
                    name = input_elem.get('name', '')
                    if field_key in name.lower():
                        form_data[name] = self.test_job_offer[field_key]
                        break
        
        print(f"📤 Submitting job offer with data: {form_data}")
        
        # Submit form
        response = self.session.post(new_offer_url, data=form_data, allow_redirects=False)
        
        if response.status_code in [302, 301]:
            print("✅ Job offer created successfully")
            
            # Try to extract job offer ID from redirect or session
            # This might require checking the partner job offer list
            self._extract_created_job_offer_id()
            return True
        else:
            print(f"❌ Failed to create job offer: {response.status_code}")
            print(f"Response content: {response.text[:1000]}")
            return False

    def _extract_created_job_offer_id(self):
        """Try to extract the ID of the newly created job offer"""
        try:
            # Visit partner job offer list to find the newest offer
            list_url = f"{self.base_url}/partner/job-offers"
            response = self.session.get(list_url)
            
            if response.status_code == 200:
                # Look for job offer with our test title
                soup = BeautifulSoup(response.text, 'html.parser')
                
                # Look for links or data attributes containing job offer IDs
                for link in soup.find_all('a', href=True):
                    if self.test_job_offer['title'] in link.get_text():
                        href = link['href']
                        # Extract ID from URL like /job-offers/123 or /partner/job-offers/123
                        match = re.search(r'/job-offers/(\d+)', href)
                        if match:
                            self.created_job_offer_id = match.group(1)
                            print(f"📋 Found created job offer ID: {self.created_job_offer_id}")
                            return
                            
        except Exception as e:
            print(f"⚠️ Could not extract job offer ID: {e}")

    def approve_job_offer_as_admin(self) -> bool:
        """Approve the created job offer as admin"""
        print("✅ Attempting to approve job offer as admin...")
        
        # Access admin job offer list
        admin_list_url = f"{self.base_url}/admin/job-offer"
        response = self.session.get(admin_list_url)
        
        if response.status_code != 200:
            print(f"❌ Failed to access admin job offer list: {response.status_code}")
            return False
        
        soup = BeautifulSoup(response.text, 'html.parser')
        
        # Find the job offer in the list (look for our test title or ID)
        job_offer_id = None
        
        # First, try to use the extracted ID
        if self.created_job_offer_id:
            job_offer_id = self.created_job_offer_id
        else:
            # Search for the job offer by title
            for element in soup.find_all(text=True):
                if self.test_job_offer['title'] in str(element):
                    # Try to find nearby ID information
                    parent = element.parent if element.parent else soup
                    for link in parent.find_all('a', href=True):
                        match = re.search(r'/admin/job-offer/(\d+)', link['href'])
                        if match:
                            job_offer_id = match.group(1)
                            break
                    if job_offer_id:
                        break
        
        if not job_offer_id:
            print("❌ Could not find the created job offer in admin list")
            return False
        
        print(f"🎯 Found job offer to approve: ID {job_offer_id}")
        
        # Find the approve form for this job offer
        approve_forms = soup.find_all('form')
        approve_form = None
        csrf_token = None
        
        for form in approve_forms:
            action = form.get('action', '')
            if f'/{job_offer_id}/approve' in action:
                approve_form = form
                csrf_token_input = form.find('input', {'name': '_token'})
                if csrf_token_input:
                    csrf_token = csrf_token_input.get('value')
                break
        
        if not approve_form or not csrf_token:
            print("❌ Could not find approve form or CSRF token")
            return False
        
        # Submit approval
        approve_url = f"{self.base_url}/admin/job-offer/{job_offer_id}/approve"
        approve_data = {
            '_token': csrf_token
        }
        
        response = self.session.post(approve_url, data=approve_data, allow_redirects=False)
        
        if response.status_code in [302, 301]:
            print("✅ Job offer approved successfully")
            return True
        else:
            print(f"❌ Failed to approve job offer: {response.status_code}")
            print(f"Response content: {response.text[:500]}")
            return False

    def run_full_workflow_test(self) -> bool:
        """Run the complete workflow test"""
        print("🚀 Starting UniLearn Job Offer Workflow Test")
        print("=" * 60)
        
        try:
            # Step 1: Partner login and job offer creation
            if not self.login_user(
                self.partner_credentials['email'], 
                self.partner_credentials['password'], 
                "Partner"
            ):
                return False
            
            if not self.create_job_offer_as_partner():
                return False
            
            # Logout partner
            self.logout_user()
            
            # Wait a moment for the system to process
            time.sleep(2)
            
            # Step 2: Admin login and approval
            if not self.login_user(
                self.admin_credentials['email'], 
                self.admin_credentials['password'], 
                "Admin"
            ):
                return False
            
            if not self.approve_job_offer_as_admin():
                return False
            
            print("=" * 60)
            print("🎉 All workflow tests completed successfully!")
            return True
            
        except Exception as e:
            print(f"💥 Test failed with exception: {e}")
            return False
        
        finally:
            # Cleanup
            self.logout_user()

    def cleanup_test_data(self):
        """Clean up test data if needed"""
        # This would typically involve deleting the test job offer
        # For now, we'll just logout
        try:
            self.logout_user()
        except:
            pass

def main():
    """Main function to run the test"""
    tester = UniLearnTester()
    
    try:
        success = tester.run_full_workflow_test()
        
        if success:
            print("\n✅ TEST PASSED: Job offer workflow completed successfully")
            exit(0)
        else:
            print("\n❌ TEST FAILED: Job offer workflow encountered errors")
            exit(1)
            
    except KeyboardInterrupt:
        print("\n🛑 Test interrupted by user")
        exit(1)
    except Exception as e:
        print(f"\n💥 Test crashed: {e}")
        exit(1)
    finally:
        tester.cleanup_test_data()

if __name__ == "__main__":
    main()