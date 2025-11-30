import sys
import mysql.connector
import json
import os
import requests
import re
from dotenv import load_dotenv

# Disable warnings
import urllib3
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

load_dotenv()

# --- CONFIG ---
DB_CONFIG = {
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'host': os.getenv('DB_HOST', 'localhost'),
    'database': os.getenv('DB_NAME', 'resume_reader')
}

GEMINI_API_KEY = os.getenv('GEMINI_API_KEY', '')
GEMINI_URL = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" + GEMINI_API_KEY

def get_candidates(ids):
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor(dictionary=True)
    
    # Securely construct query for IN clause
    format_strings = ','.join(['%s'] * len(ids))
    query = f"""
        SELECT c.name, jp.job_name, c.education, c.skills, c.experience, r.score_overall
        FROM candidate c
        JOIN job_position jp ON c.job_id = jp.job_id
        LEFT JOIN report r ON c.candidate_id = r.candidate_id
        WHERE c.candidate_id IN ({format_strings})
    """
    
    cursor.execute(query, tuple(ids))
    results = cursor.fetchall()
    conn.close()
    return results

def call_gemini(prompt):
    headers = {'Content-Type': 'application/json'}
    payload = {
        "contents": [{"parts": [{"text": prompt}]}],
        "safetySettings": [
            {"category": "HARM_CATEGORY_HARASSMENT", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_HATE_SPEECH", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold": "BLOCK_NONE"}
        ]
    }
    
    try:
        response = requests.post(GEMINI_URL, headers=headers, json=payload, verify=False, timeout=30)
        if response.status_code == 200:
            data = response.json()
            if 'candidates' in data and data['candidates']:
                return data['candidates'][0]['content']['parts'][0]['text']
    except Exception as e:
        return f"Error contacting AI: {str(e)}"
    return "AI analysis failed."

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"status": "error", "message": "No IDs provided"}))
        return

    ids_str = sys.argv[1]
    ids = [int(x) for x in ids_str.split(',') if x.isdigit()]
    
    if not ids:
        print(json.dumps({"status": "error", "message": "Invalid IDs"}))
        return

    candidates = get_candidates(ids)
    
    if not candidates:
        print(json.dumps({"status": "error", "message": "Candidates not found in DB"}))
        return

    # Build Prompt
    prompt = "Role: Expert HR Recruiter.\nTask: Compare the following candidates and recommend the best fit.\n\n"
    
    for i, c in enumerate(candidates):
        # Truncate long text to fit context window
        edu = (c['education'] or 'N/A')[:2000]
        skills = (c['skills'] or 'N/A')[:2000]
        exp = (c['experience'] or 'N/A')[:2000]
        
        prompt += f"--- CANDIDATE {i+1}: {c['name']} ---\n"
        prompt += f"Applied For: {c['job_name']}\n"
        prompt += f"System Score: {c['score_overall']}\n"
        prompt += f"Education: {edu}\n"
        prompt += f"Skills: {skills}\n"
        prompt += f"Experience: {exp}\n\n"

    prompt += "Instructions:\n"
    prompt += "1. Highlight key strengths and weaknesses of each relative to the role.\n"
    prompt += "2. Directly compare their experience and skills.\n"
    prompt += "3. Conclude with a clear recommendation: Who is the better hire and why?\n"
    prompt += "4. Keep the tone professional and objective."

    analysis = call_gemini(prompt)
    
    print(json.dumps({"status": "success", "analysis": analysis}))

if __name__ == "__main__":
    main()