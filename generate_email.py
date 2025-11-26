import sys
import mysql.connector
import json
import requests
import random
import string
import urllib3
import warnings
import os.path
import datetime

from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials
from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build

# --- SUPPRESS SSL WARNINGS ---
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
warnings.filterwarnings("ignore", category=urllib3.exceptions.InsecureRequestWarning)

# --- DB CONFIG ---
DB_CONFIG = {
    'user': 'root',
    'password': '',
    'host': 'localhost',
    'database': 'resume_reader'
}

# --- GEMINI CONFIG ---
GEMINI_API_KEY = "" 
GEMINI_URL = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={GEMINI_API_KEY}"

# --- GOOGLE CALENDAR API SCOPES ---
SCOPES = ['https://www.googleapis.com/auth/calendar']

def get_google_meet_link(summary='Interview', description='Interview via Resume Reader', start_time=None):
    """Generates a Google Meet link using the Google Calendar API."""
    creds = None
    
    # Check for existing token
    if os.path.exists('token.json'):
        try:
            creds = Credentials.from_authorized_user_file('token.json', SCOPES)
        except Exception:
            creds = None
    
    # If no valid credentials, log in
    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            try:
                creds.refresh(Request())
            except Exception:
                creds = None

        if not creds:
            if not os.path.exists('credentials.json'):
                return "Error: credentials.json not found."
            
            try:
                flow = InstalledAppFlow.from_client_secrets_file('credentials.json', SCOPES)
                creds = flow.run_local_server(port=8080)
            except Exception as e:
                return f"Error during OAuth: {str(e)}"
        
        # Save the credentials
        with open('token.json', 'w') as token:
            token.write(creds.to_json())

    try:
        service = build('calendar', 'v3', credentials=creds)

        # Time logic
        if not start_time:
             tomorrow = datetime.date.today() + datetime.timedelta(days=1)
             start_dt = datetime.datetime.combine(tomorrow, datetime.time(10, 0))
        else:
             try:
                 start_dt = datetime.datetime.fromisoformat(start_time)
             except ValueError:
                 tomorrow = datetime.date.today() + datetime.timedelta(days=1)
                 start_dt = datetime.datetime.combine(tomorrow, datetime.time(10, 0))

        end_dt = start_dt + datetime.timedelta(hours=1)

        event_body = {
            'summary': summary,
            'description': description,
            'start': {
                'dateTime': start_dt.isoformat(),
                'timeZone': 'UTC', 
            },
            'end': {
                'dateTime': end_dt.isoformat(),
                'timeZone': 'UTC',
            },
            'conferenceData': {
                'createRequest': {
                    'requestId': f"req-{int(datetime.datetime.now().timestamp())}",
                    'conferenceSolutionKey': {'type': 'hangoutsMeet'}
                }
            },
        }

        # IMPORTANT: conferenceDataVersion=1 is REQUIRED to actually generate the link
        event = service.events().insert(calendarId='primary', body=event_body, conferenceDataVersion=1).execute()
        
        # Extract the link
        meet_link = event.get('conferenceData', {}).get('entryPoints', [{}])[0].get('uri')
        
        if not meet_link:
            return "Error: Calendar event created, but Meet link was not returned."
            
        return meet_link

    except Exception as e:
        return f"Error generating link: {str(e)}"

def generate_email(candidate_id, action, interview_date=None):
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)

    try:
        # 1. Fetch Candidate Data & User Company
        # We join candidate table with user table on email_user to get the company name
        query = """
            SELECT c.name, c.email, j.job_name, u.company
            FROM candidate c
            JOIN job_position j ON c.job_id = j.job_id
            LEFT JOIN user u ON c.email_user = u.email
            WHERE c.candidate_id = %s
        """
        cursor.execute(query, (candidate_id,))
        data = cursor.fetchone()
        
        if not data:
            print(json.dumps({"status": "error", "message": "Candidate not found"}))
            return

        name = data['name']
        job = data['job_name']
        email_addr = data['email']
        # Use fetched company or fallback to 'Resume Reader' if null or empty
        company_name = data.get('company') if data.get('company') else 'Resume Reader'
        
        # 2. Prepare Prompt based on Action
        if action == 'accept':
            meet_link = get_google_meet_link(
                summary=f"Interview: {name} - {job}", 
                description=f"Interview for {job} at {company_name}", 
                start_time=interview_date
            )
            
            link_text = f"Meeting Link: {meet_link}" if "Error" not in str(meet_link) else "[Link will be sent separately]"

            prompt = (
                f"Write a professional email invitation for an interview at {company_name}.\n"
                f"Candidate: {name}\nRole: {job}\n"
                f"Time: {interview_date}\n"
                f"{link_text}\n"
                f"Tone: Welcoming and professional.\n"
                f"Content: Congratulate them on their application. "
                f"State clearly that we at {company_name} are impressed with their resume and would like to invite them for an interview. "
                f"Propose the interview time and provide the link. "
                f"IMPORTANT: Do NOT include a subject line in the output. Output ONLY the email body text."
            )
            subject = f"Interview Invitation: {job} at {company_name}"
            
        else: # reject
            meet_link = None
            prompt = (
                f"Write a polite, empathetic rejection email from {company_name}.\n"
                f"Candidate: {name}\nRole: {job}\n"
                f"Tone: Professional, kind, encouraging, not harsh.\n"
                f"Content: Thank them for applying to {company_name}. Mention we were impressed with their resume, but we have decided to move forward with other candidates who more closely match our current needs. Wish them luck in their future endeavors. "
                f"IMPORTANT: Do NOT include a subject line in the output. Output ONLY the email body text."
            )
            subject = f"Update regarding your application for {job} at {company_name}"

        # 3. Call Gemini
        payload = {"contents": [{"parts": [{"text": prompt}]}]}
        headers = {'Content-Type': 'application/json'}
        
        response = requests.post(GEMINI_URL, headers=headers, json=payload, verify=False)
        email_body = "Error generating email."
        
        if response.status_code == 200:
            result = response.json()
            if 'candidates' in result:
                email_body = result['candidates'][0]['content']['parts'][0]['text']
        
        # 4. Return Result including the fetched email
        print(json.dumps({
            "status": "success",
            "subject": subject,
            "body": email_body,
            "meet_link": meet_link,
            "email_to": email_addr
        }))

    except Exception as e:
        print(json.dumps({"status": "error", "message": str(e)}))
    finally:
        if 'cursor' in locals() and cursor:
            cursor.close()
        if 'conn' in locals() and conn:
            conn.close()

if __name__ == "__main__":
    if len(sys.argv) > 2:
        cid = sys.argv[1]
        act = sys.argv[2]
        date = sys.argv[3] if len(sys.argv) > 3 else None
        generate_email(cid, act, date)
    else:
        print(json.dumps({"status": "error", "message": "Invalid arguments"}))