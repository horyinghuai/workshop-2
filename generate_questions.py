import sys
import mysql.connector
import os
import requests
import json
from dotenv import load_dotenv

# Disable warnings
import urllib3
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

load_dotenv()

# --- CONFIGURATION ---
DB_CONFIG = {
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'host': os.getenv('DB_HOST', 'localhost'),
    'database': os.getenv('DB_NAME', 'resume_reader')
}

GEMINI_API_KEY = os.getenv('GEMINI_API_KEY', '')
GEMINI_BASE_URL = "https://generativelanguage.googleapis.com/v1beta/models/"
VALID_MODELS = ["gemini-2.0-flash", "gemini-1.5-flash", "gemini-1.5-pro"]

def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)

def call_gemini(prompt):
    headers = {'Content-Type': 'application/json'}
    payload = {
        "contents": [{"parts": [{"text": prompt}]}],
        "safetySettings": [
            {"category": "HARM_CATEGORY_HARASSMENT", "threshold": "BLOCK_NONE"},
        ]
    }

    for model in VALID_MODELS:
        url = f"{GEMINI_BASE_URL}{model}:generateContent?key={GEMINI_API_KEY}"
        try:
            response = requests.post(url, headers=headers, json=payload, timeout=30, verify=False)
            if response.status_code == 200:
                result = response.json()
                if 'candidates' in result and len(result['candidates']) > 0:
                    return result['candidates'][0]['content']['parts'][0]['text'].strip()
        except Exception as e:
            print(f"Error with {model}: {e}")
            continue
    return None

def generate_questions(candidate_id):
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)

    try:
        # 1. Fetch Candidate & Job Data
        cursor.execute("SELECT * FROM candidate WHERE candidate_id = %s", (candidate_id,))
        candidate = cursor.fetchone()
        
        if not candidate:
            print("Candidate not found.")
            return

        job_id = candidate['job_id']
        cursor.execute("SELECT * FROM job_position WHERE job_id = %s", (job_id,))
        job = cursor.fetchone() or {}

        # 2. Prepare Prompt
        cand_summary = (
            f"Skills: {candidate.get('skills', 'N/A')}\n"
            f"Experience: {candidate.get('experience', 'N/A')}\n"
            f"Education: {candidate.get('education', 'N/A')}\n"
            f"Projects/Others: {candidate.get('others', 'N/A')}"
        )

        job_summary = (
            f"Role: {job.get('job_name', 'N/A')}\n"
            f"Requirements: {job.get('skills', '')} {job.get('experience', '')}"
        )

        prompt = (
            f"Role: Senior Technical Interviewer.\n"
            f"Task: Generate a list of 8 interview questions (5 Technical, 3 Behavioral) for a candidate applying for {job.get('job_name')}.\n"
            f"Job Requirements: {job_summary}\n"
            f"Candidate Resume Summary: {cand_summary}\n"
            f"Instructions:\n"
            f"- Identify gaps between the candidate's resume and the job requirements.\n"
            f"- Output strictly a numbered list of questions."
        )

        # 3. Call API
        print("Generating questions...")
        generated_text = call_gemini(prompt)
        
        if not generated_text:
            generated_text = "Error: Could not generate questions from AI."

        # 4. Update Database (Targeting the interview table)
        # We update the latest interview record for this candidate
        sql = """
            UPDATE interview 
            SET questions = %s 
            WHERE candidate_id = %s 
            ORDER BY interview_date DESC LIMIT 1
        """
        cursor.execute(sql, (generated_text, candidate_id))
        conn.commit()
        print("Questions saved to interview table successfully.")

    except Exception as e:
        print(f"Error: {str(e)}")
    finally:
        cursor.close()
        conn.close()

if __name__ == "__main__":
    if len(sys.argv) > 1:
        generate_questions(sys.argv[1])
    else:
        print("No candidate ID provided.")