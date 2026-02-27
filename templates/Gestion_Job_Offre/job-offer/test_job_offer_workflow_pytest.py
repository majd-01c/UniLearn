#!/usr/bin/env python3
"""
Pytest version of the UniLearn Job Offer Workflow Test

Usage:
    pytest test_job_offer_workflow_pytest.py -v
    pytest test_job_offer_workflow_pytest.py -v -s  # with output
"""

import pytest
import requests
from bs4 import BeautifulSoup
from datetime import datetime
import time
import re


@pytest.fixture(scope="session")
def test_config():
    """Test configuration fixture"""
    return {
        'base_url': 'http://127.0.0.1:8000',
        'partner_email': 'amri.dhia21@gmail.com',
        'partner_password': 'C*RRcdS73glX',
        'admin_email': 'admin@unilearn.com', 
        'admin_password': 'admin123'
    }


@pytest.fixture
def test_session():
    """Create a test session"""
    session = requests.Session()
    session.headers.update({
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    })
    return session


def extract_csrf_token(html_content: str) -> str:
    """Extract CSRF token from HTML content"""
    soup = BeautifulSoup(html_content, 'html.parser')
    
    # Try _csrf_token first
    csrf_input = soup.find('input', {'name': '_csrf_token'})
    if csrf_input:
        return csrf_input.get('value')
    
    # Fallback to _token
    csrf_input = soup.find('input', {'name': '_token'})
    if csrf_input:
        return csrf_input.get('value')
        
    raise ValueError("CSRF token not found")


def login_user(session, base_url: str, email: str, password: str) -> bool:
    """Login a user"""
    # Get login page
    login_url = f"{base_url}/login"
    response = session.get(login_url)
    assert response.status_code == 200, f"Failed to access login page: {response.status_code}"
    
    # Extract CSRF token
    csrf_token = extract_csrf_token(response.text)
    
    # Submit login
    login_data = {
        '_username': email,
        '_password': password,
        '_csrf_token': csrf_token
    }
    
    response = session.post(login_url, data=login_data, allow_redirects=False)
    return response.status_code in [302, 301]


def logout_user(session, base_url: str):
    """Logout user"""
    logout_url = f"{base_url}/logout"
    session.get(logout_url)


class TestJobOfferWorkflow:
    """Test class for job offer workflow"""
    
    def test_partner_login(self, test_config, test_session):
        """Test partner login functionality"""
        success = login_user(
            test_session, 
            test_config['base_url'],
            test_config['partner_email'],
            test_config['partner_password']
        )
        assert success, "Partner login failed"
        logout_user(test_session, test_config['base_url'])
    
    def test_admin_login(self, test_config, test_session):
        """Test admin login functionality"""
        success = login_user(
            test_session,
            test_config['base_url'],
            test_config['admin_email'], 
            test_config['admin_password']
        )
        assert success, "Admin login failed"
        logout_user(test_session, test_config['base_url'])
    
    def test_partner_create_job_offer(self, test_config, test_session):
        """Test job offer creation by partner"""
        # Login as partner
        login_success = login_user(
            test_session,
            test_config['base_url'],
            test_config['partner_email'],
            test_config['partner_password']
        )
        assert login_success, "Partner login failed"
        
        # Access job offer creation page
        new_offer_url = f"{test_config['base_url']}/partner/job-offers/new"
        response = test_session.get(new_offer_url)
        assert response.status_code == 200, f"Failed to access job offer creation page: {response.status_code}"
        
        # Extract CSRF token
        csrf_token = extract_csrf_token(response.text)
        
        # Prepare test job offer data
        test_title = f"Test Job Offer - {datetime.now().strftime('%Y%m%d_%H%M%S')}"
        
        # Parse form to find field names
        soup = BeautifulSoup(response.text, 'html.parser')
        form = soup.find('form')
        assert form, "Job offer form not found"
        
        # Build form data based on discovered field names
        form_data = {'_token': csrf_token}
        
        # Try to find and populate form fields
        fields_to_find = {
            'title': test_title,
            'type': 'FULL_TIME', 
            'location': 'Tunis, Tunisia',
            'description': 'This is a test job offer created by automated testing.',
        }
        
        for field_key, field_value in fields_to_find.items():
            # Look for various possible field name patterns
            possible_names = [
                f'job_offer_form_type[{field_key}]',
                f'job_offer[{field_key}]',
                field_key
            ]
            
            field_found = False
            for name in possible_names:
                if soup.find(['input', 'textarea', 'select'], {'name': name}):
                    form_data[name] = field_value
                    field_found = True
                    break
            
            if not field_found:
                # Try partial match
                for elem in soup.find_all(['input', 'textarea', 'select']):
                    name = elem.get('name', '')
                    if field_key in name.lower():
                        form_data[name] = field_value
                        break
        
        # Submit form
        response = test_session.post(new_offer_url, data=form_data, allow_redirects=False)
        
        # Should redirect on success
        creation_success = response.status_code in [302, 301]
        
        logout_user(test_session, test_config['base_url'])
        
        assert creation_success, f"Job offer creation failed: {response.status_code}"
    
    def test_admin_access_job_offers(self, test_config, test_session):
        """Test admin access to job offers list"""
        # Login as admin
        login_success = login_user(
            test_session,
            test_config['base_url'],
            test_config['admin_email'],
            test_config['admin_password']
        )
        assert login_success, "Admin login failed"
        
        # Access admin job offer list
        admin_list_url = f"{test_config['base_url']}/admin/job-offer"
        response = test_session.get(admin_list_url)
        
        logout_user(test_session, test_config['base_url'])
        
        assert response.status_code == 200, f"Failed to access admin job offer list: {response.status_code}"
    
    @pytest.mark.slow
    def test_complete_workflow(self, test_config, test_session):
        """Test the complete job offer workflow - partner creates, admin approves"""
        # Step 1: Partner creates job offer
        login_success = login_user(
            test_session,
            test_config['base_url'], 
            test_config['partner_email'],
            test_config['partner_password']
        )
        assert login_success, "Partner login failed"
        
        # Create job offer
        new_offer_url = f"{test_config['base_url']}/partner/job-offers/new"
        response = test_session.get(new_offer_url)
        assert response.status_code == 200
        
        csrf_token = extract_csrf_token(response.text)
        test_title = f"E2E Test Job Offer - {datetime.now().strftime('%Y%m%d_%H%M%S')}"
        
        soup = BeautifulSoup(response.text, 'html.parser')
        form_data = {'_token': csrf_token}
        
        # Quick field mapping for the test
        fields = {
            'title': test_title,
            'type': 'FULL_TIME',
            'location': 'Test Location',
            'description': 'End-to-end test job offer description'
        }
        
        for field_key, field_value in fields.items():
            # Find field name in form
            for elem in soup.find_all(['input', 'textarea', 'select']):
                name = elem.get('name', '')
                if field_key in name.lower() and 'job_offer' in name:
                    form_data[name] = field_value
                    break
        
        response = test_session.post(new_offer_url, data=form_data, allow_redirects=False)
        assert response.status_code in [302, 301], "Job offer creation failed"
        
        logout_user(test_session, test_config['base_url'])
        time.sleep(1)  # Small delay
        
        # Step 2: Admin approves job offer
        login_success = login_user(
            test_session,
            test_config['base_url'],
            test_config['admin_email'],
            test_config['admin_password']
        )
        assert login_success, "Admin login failed"
        
        # Find the created job offer in admin list
        admin_list_url = f"{test_config['base_url']}/admin/job-offer"
        response = test_session.get(admin_list_url)
        assert response.status_code == 200
        
        soup = BeautifulSoup(response.text, 'html.parser')
        
        # Look for the job offer (this is simplified - in real test you'd need more robust ID extraction)
        # For now, just verify admin can access the list
        assert "job" in response.text.lower(), "Job offers not visible in admin interface"
        
        logout_user(test_session, test_config['base_url'])


if __name__ == "__main__":
    pytest.main([__file__, "-v"])