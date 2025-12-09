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

# --- LOAD ENV ROBUSTLY ---
# Get the directory where this script is located
script_dir = os.path.dirname(os.path.abspath(__file__))
# Load .env from that directory to ensure we find it even if called from PHP
load_dotenv(os.path.join(script_dir, '.env'))

# --- CONFIG ---
DB_CONFIG = {
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'host': os.getenv('DB_HOST', 'localhost'),
    'database': os.getenv('DB_NAME', 'resume_reader')
}

GEMINI_API_KEY = os.getenv('GEMINI_API_KEY', '')
# Base URL (we append key later)
GEMINI_URL_BASE = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key="

def get_candidates(ids):
    try:
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
    except Exception as e:
        return None

def call_gemini(prompt):
    if not GEMINI_API_KEY:
        return "Error: GEMINI_API_KEY is missing in .env file."

    full_url = GEMINI_URL_BASE + GEMINI_API_KEY
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
        response = requests.post(full_url, headers=headers, json=payload, verify=False, timeout=30)
        
        if response.status_code == 200:
            data = response.json()
            if 'candidates' in data and data['candidates']:
                return data['candidates'][0]['content']['parts'][0]['text']
            else:
                return "AI returned no content. Response: " + str(data)
        else:
            return f"AI Error ({response.status_code}): {response.text}"
            
    except Exception as e:
        return f"Error contacting AI: {str(e)}"

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"status": "error", "message": "No IDs provided"}))
        return

    ids_str = sys.argv[1]
    # Clean and parse IDs
    try:
        ids = [int(x) for x in ids_str.split(',') if x.strip().isdigit()]
    except:
        print(json.dumps({"status": "error", "message": "Invalid ID format"}))
        return
    
    if not ids:
        print(json.dumps({"status": "error", "message": "No valid IDs parsed"}))
        return

    candidates = get_candidates(ids)
    
    if not candidates:
        print(json.dumps({"status": "error", "message": "Candidates not found in DB or DB Error"}))
        return

    # Build Prompt
    prompt = "Role: Expert HR Recruiter.\nTask: Compare the following candidates and recommend the best fit.\n\n"
    
    for i, c in enumerate(candidates):
        # Truncate long text to fit context window
        edu = (c['education'] or 'N/A')[:1500]
        skills = (c['skills'] or 'N/A')[:1500]
        exp = (c['experience'] or 'N/A')[:1500]
        
        prompt += f"--- CANDIDATE {i+1}: {c['name']} ---\n"
        prompt += f"Applied For: {c['job_name']}\n"
        prompt += f"System Score: {c['score_overall']}\n"
        prompt += f"Education: {edu}\n"
        prompt += f"Skills: {skills}\n"
        prompt += f"Experience: {exp}\n\n"

    prompt += "Instructions:\n"
    prompt += "1. Do NOT include any intro text like 'Okay, let's analyze these candidates' and '---'.\n"
    prompt += "2. Highlight key strengths and weaknesses of each relative to the role.\n"
    prompt += "3. Directly compare their experience and skills.\n"
    prompt += "4. Conclude with a clear recommendation: Who is the better hire and why?\n"
    prompt += "5. Keep the tone professional and objective."

    analysis = call_gemini(prompt)
    
    # Return success even if analysis contains an error message, so the UI displays it
    print(json.dumps({"status": "success", "analysis": analysis}))

if __name__ == "__main__":
    main()