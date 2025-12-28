import sys
import mysql.connector
import json
import os

# --- NEW: IMPORT DOTENV ---
from dotenv import load_dotenv

# --- LOAD ENV VARIABLES ---
load_dotenv()

# --- DB CONFIG ---
DB_CONFIG = {
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'host': os.getenv('DB_HOST', 'localhost'),
    'database': os.getenv('DB_NAME', 'resume_reader')
}

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
        
        meet_link = None 

        # 2. Generate Email Content using Templates
        if action == 'accept':
            subject = f"Interview Invitation: {job} at {company_name}"
            
            # Logic to separate Date and Time from the input string
            # Assumes input format is like "YYYY-MM-DD HH:MM:SS" or similar
            date_display = "[Date]"
            time_display = "[Time]"

            if interview_date:
                parts = str(interview_date).split(' ')
                if len(parts) >= 2:
                    date_display = parts[0]
                    # Join the rest in case time has AM/PM or other parts
                    time_display = " ".join(parts[1:])
                elif 'T' in str(interview_date): # Handle ISO format YYYY-MM-DDTHH:MM
                    parts = str(interview_date).split('T')
                    date_display = parts[0]
                    time_display = parts[1]
                else:
                    date_display = interview_date

            email_body = (
                f"Dear {name},\n\n"
                f"Thank you for applying to the {job} position at {company_name}. We were impressed with your application and would like to invite you for an interview to discuss your qualifications further.\n\n"
                f"Date: {date_display}\n"
                f"Time: {time_display}\n\n"
                f"Please join the meeting using the link below:\n"
                f"[Meeting Link]\n\n"
                f"If this time does not work for you, please let us know so we can reschedule.\n\n"
                f"Best regards,\n"
                f"{company_name} Recruitment Team"
            )
            
        else: # reject
            subject = f"Update regarding your application for {job} at {company_name}"
            
            email_body = (
                f"Dear {name},\n\n"
                f"Thank you for giving us the opportunity to consider your application for the {job} role at {company_name}. We appreciate the time and effort you put into the process.\n\n"
                f"We have reviewed your background and qualifications, and while we were impressed, we have decided to move forward with other candidates who more closely match our current needs.\n\n"
                f"We wish you the best in your future endeavors.\n\n"
                f"Sincerely,\n"
                f"{company_name} Recruitment Team"
            )

        # 3. Return Result (meet_link will be null/None, to be filled by PHP if needed or user)
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