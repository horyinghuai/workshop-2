import sys
import mysql.connector
import json
import requests
import random
import string
import urllib3
import warnings

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
GEMINI_API_KEY = "AIzaSyB8TL357EU3xqAD3zour86OkeYtj9WLQcw" 
GEMINI_URL = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={GEMINI_API_KEY}"

def get_random_meet_link():
    """Generates a random Google Meet style link (xxx-yyyy-zzz)"""
    def rand_str(length):
        # Ensure only lowercase letters for Google Meet codes
        return ''.join(random.choices(string.ascii_lowercase, k=length))
    return f"https://meet.google.com/{rand_str(3)}-{rand_str(4)}-{rand_str(3)}"

def generate_email(candidate_id, action, interview_date=None):
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)

    try:
        # 1. Fetch Candidate Data & User Company
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
        company_name = data.get('company') if data.get('company') else 'Resume Reader'
        
        # 2. Prepare Prompt based on Action
        if action == 'accept':
            meet_link = get_random_meet_link()
            prompt = (
                f"Write a professional email invitation for an interview at {company_name}.\n"
                f"Candidate: {name}\nRole: {job}\n"
                f"Time: {interview_date}\n"
                f"Meeting Link: {meet_link}\n"
                f"Tone: Welcoming and professional.\n"
                f"Content: Congratulate them on their application. "
                f"State clearly that we at {company_name} are impressed with their resume and would like to invite them for an interview. "
                f"Propose the interview time and provide the link. Do not include the subject line."
            )
            subject = f"Interview Invitation: {job} at {company_name}"
            
        else: # reject
            meet_link = None
            prompt = (
                f"Write a polite, empathetic rejection email from {company_name}.\n"
                f"Candidate: {name}\nRole: {job}\n"
                f"Tone: Professional, kind, encouraging, not harsh.\n"
                f"Content: Thank them for applying to {company_name}. Mention we were impressed with their resume, but we have decided to move forward with other candidates who more closely match our current needs. Wish them luck in their future endeavors. Do not include the subject line."
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