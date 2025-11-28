import sys
import mysql.connector
import json
import pandas as pd
import os
import requests
import time
import re
import warnings

# --- NEW: IMPORT DOTENV ---
from dotenv import load_dotenv

# --- FIX FOR IMPORT ERROR ---
import urllib3
# Disable "InsecureRequestWarning" since we use verify=False for Localhost/XAMPP
urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

# --- LOAD ENV VARIABLES ---
load_dotenv()

# --- DEBUG: CONFIRM NEW CODE IS RUNNING ---
print("LOADED: process_report.py (Env Configured)")

# --- CONFIGURATION ---
DB_CONFIG = {
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'host': os.getenv('DB_HOST', 'localhost'),
    'database': os.getenv('DB_NAME', 'resume_reader')
}

# --- GEMINI API CONFIGURATION ---
GEMINI_API_KEY = os.getenv('GEMINI_API_KEY', '')
GEMINI_URL = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={GEMINI_API_KEY}"

# Priority Models (Start with 2.0-flash, then fall back to 1.5 if needed)
VALID_MODELS = [
    "gemini-2.0-flash",
    "gemini-1.5-flash", 
    "gemini-1.5-pro", 
    "gemini-1.0-pro"
]

# --- DEBUG LOGGING FUNCTION ---
def log_gemini_error(msg):
    try:
        with open("gemini_error_log.txt", "a") as f:
            f.write(f"{time.strftime('%Y-%m-%d %H:%M:%S')} - {msg}\n")
    except: pass

# --- PROGRESS FUNCTION ---
def update_progress(pid, percent):
    if pid:
        try:
            with open(f"progress_{pid}.txt", "w") as f:
                f.write(str(percent))
        except: pass

def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)

# UPDATED: Scoring now compares Candidate Text vs Job Requirement Text
def calculate_hybrid_score(candidate_text, job_requirement_text, field_name):
    # 1. Safety Checks
    if not candidate_text or len(str(candidate_text)) < 3: return 10.0
    if not job_requirement_text or len(str(job_requirement_text)) < 3: return 80.0 # If no requirement, assume good fit
    
    c_text = str(candidate_text).lower()
    j_text = str(job_requirement_text).lower()
    
    # --- 1. EDUCATION HIERARCHY LOGIC ---
    if field_name == 'education':
        edu_levels = {'phd': 5, 'doctorate': 5, 'master': 4, 'bachelor': 3, 'degree': 3, 'diploma': 2, 'certificate': 1}
        
        cand_level = 0
        req_level = 0
        
        for edu, val in edu_levels.items():
            if edu in c_text and val > cand_level: cand_level = val
            if edu in j_text and val > req_level: req_level = val
            
        # Bonus if candidate level >= requirement level
        if cand_level > req_level:
            return 100.0 # Overqualified is a perfect score for "minimum requirement"
        elif cand_level == req_level:
            return 95.0
        elif cand_level < req_level and cand_level > 0:
            return 60.0 # Underqualified but has some education
    
    # --- 2. SKILL / GENERAL KEYWORD MATCHING ---
    # Split job requirements by comma or spaces to get key terms
    job_keywords = [k.strip() for k in re.split(r'[,|\n]', j_text) if len(k.strip()) > 2]
    
    match_count = 0
    total_keywords = len(job_keywords)
    
    if total_keywords > 0:
        for keyword in job_keywords:
            if keyword in c_text:
                match_count += 1
        keyword_score = (match_count / total_keywords) * 100
    else:
        keyword_score = 100.0 # No specific keywords required

    # --- 3. SEMANTIC RELEVANCE (For "Other relevant skills") ---
    try:
        documents = [j_text, c_text]
        tfidf = TfidfVectorizer(stop_words='english')
        tfidf_matrix = tfidf.fit_transform(documents)
        
        # Compare Candidate (index 1) against Job (index 0)
        cosine_sim = cosine_similarity(tfidf_matrix[0:1], tfidf_matrix[1:2])
        ml_score = cosine_sim[0][0] * 100
    except:
        ml_score = keyword_score # Fallback if TF-IDF fails

    # 4. Weighted Final Score
    final_score = (keyword_score * 0.5) + (ml_score * 0.5)
    
    # Boost score slightly if semantic match is high even if keywords miss (Candidate has relevant alternatives)
    if keyword_score < 50 and ml_score > 60:
        final_score += 15 
    
    return round(min(99.0, max(10.0, final_score)), 2)

# UPDATED: Prompt now includes the Job Requirement context
def generate_llm_comment(field_name, candidate_text, job_requirement_text, score):
    if not candidate_text or len(str(candidate_text)) < 5: 
        return "No details provided for this section."

    # Construct a context-aware prompt
    prompt = (
        f"Role: HR Recruiter. \n"
        f"Task: Compare Candidate vs Job Requirement for '{field_name}'.\n\n"
        f"Job Requirement: \"{job_requirement_text}\"\n"
        f"Candidate Data: \"{candidate_text}\"\n\n"
        f"Match Score: {score}/100.\n"
        f"Instructions:\n"
        f"1. If candidate meets/exceeds requirements (e.g. Master vs Diploma), praise them.\n"
        f"2. If candidate lacks specific skills but has RELEVANT alternatives, state: 'Lacks [X] but possesses relevant skills in [Y].'\n"
        f"3. Keep it under 40 words."
    )

    payload = {
        "contents": [{"parts": [{"text": prompt}]}],
        "safetySettings": [
            {"category": "HARM_CATEGORY_HARASSMENT", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_HATE_SPEECH", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold": "BLOCK_NONE"}
        ]
    }
    
    headers = {'Content-Type': 'application/json'}

    # Try the specific 2.0 Flash URL first
    try:
        response = requests.post(GEMINI_URL, headers=headers, json=payload, timeout=20, verify=False)
        if response.status_code == 200:
            result = response.json()
            if 'candidates' in result and len(result['candidates']) > 0:
                return result['candidates'][0]['content']['parts'][0]['text'].strip()
    except Exception:
        pass # Fail silently to fallback loop

    # Fallback loop
    for model_name in VALID_MODELS:
        url = f"https://generativelanguage.googleapis.com/v1beta/models/{model_name}:generateContent?key={GEMINI_API_KEY}"
        try:
            response = requests.post(url, headers=headers, json=payload, timeout=20, verify=False)
            if response.status_code == 200:
                result = response.json()
                if 'candidates' in result and len(result['candidates']) > 0:
                    return result['candidates'][0]['content']['parts'][0]['text'].strip()
            elif response.status_code == 429:
                time.sleep(2)
                continue
            elif response.status_code == 404:
                continue
        except Exception as e:
            log_gemini_error(f"Exception: {str(e)}")
            continue

    # Fallback
    return f"Candidate provided details. Match score: {score}/100. (AI Analysis Unavailable)"

# --- UPDATED CONFIDENCE LOGIC ---
def calculate_confidence(row):
    confidence = 0.0 # Start at 0
    
    # Increased Thresholds significantly to make 99% hard to get
    weights = {
        'experience': {'threshold': 50, 'points': 35}, 
        'education': {'threshold': 15, 'points': 20},
        'skills': {'threshold': 10, 'points': 20},
        'language': {'threshold': 5, 'points': 10},
        'others': {'threshold': 10, 'points': 10}
    }
    
    # Bonus: Is the resume balanced?
    sections_present = 0

    for field, criteria in weights.items():
        content = str(row.get(field, ""))
        # Filter out "None" or "N/A"
        if content.lower() in ['none', 'n/a', ''] or len(content) < 3:
            word_count = 0
        else:
            word_count = len(content.split())
            sections_present += 1
        
        if word_count >= criteria['threshold']:
            confidence += criteria['points']
        elif word_count > 0:
            # Linear scaling: e.g. if you have 25/50 words, you get half points
            ratio = word_count / criteria['threshold']
            confidence += (criteria['points'] * ratio)

    # Penalty if sections are missing
    if sections_present < 3:
        confidence -= 15

    return round(min(99.0, max(10.0, confidence)), 2)

def process_candidate(candidate_id, pid=None):
    update_progress(pid, 5) 
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    
    try:
        update_progress(pid, 10)
        
        # 1. Fetch Candidate Data
        cursor.execute("SELECT * FROM candidate WHERE candidate_id = %s", (candidate_id,))
        candidate = cursor.fetchone()
        if not candidate: 
            print(json.dumps({"status": "error", "message": "Candidate not found"}))
            return

        update_progress(pid, 20)

        # 2. Fetch Job Requirements based on candidate's job_id
        job_id = candidate['job_id']
        cursor.execute("SELECT * FROM job_position WHERE job_id = %s", (job_id,))
        job_reqs = cursor.fetchone()
        
        # Handle case if job is deleted/missing
        if not job_reqs:
            # Fallback to empty strings to prevent crash, score will rely on text presence
            job_reqs = {k: "" for k in ['education', 'skills', 'experience', 'language', 'others', 'job_name']}

        update_progress(pid, 30)
        
        # Fields to analyze (Column Name in DB)
        fields = ['education', 'skills', 'experience', 'language', 'others']
        scores = {}
        comments = {}
        
        step = 60 / len(fields)
        current_p = 30

        for field in fields:
            # Data from Candidate
            cand_content = candidate.get(field, "")
            # Data from Job Position (Requirement)
            job_content = job_reqs.get(field, "")
            
            # Pass field_name to apply Education Hierarchy Logic
            scores[field] = calculate_hybrid_score(cand_content, job_content, field)
            
            # Generate Comment comparing Candidate vs Job
            comments[field] = generate_llm_comment(field, cand_content, job_content, scores[field])
            
            time.sleep(1) 
            current_p += step
            update_progress(pid, int(current_p))

        # Overall Score is average of component scores
        scores['overall'] = sum(scores.values()) / len(scores)
        
        # Overall Comment uses Job Title context
        job_title = job_reqs.get('job_name', 'the role')
        comments['overall'] = generate_llm_comment(f"Overall Profile for {job_title}", str(candidate), str(job_reqs), scores['overall'])
        
        confidence = calculate_confidence(candidate)

        update_progress(pid, 95)

        # Save to DB
        cursor.execute("SELECT report_id FROM report WHERE candidate_id = %s", (candidate_id,))
        exists = cursor.fetchone()

        if exists:
            sql = """UPDATE report SET score_overall=%s, ai_comments_overall=%s, score_education=%s, ai_comments_education=%s, score_skills=%s, ai_comments_skills=%s, score_experience=%s, ai_comments_experience=%s, score_language=%s, ai_comments_language=%s, score_others=%s, ai_comments_others=%s, ai_confidence_level=%s WHERE candidate_id=%s"""
        else:
            sql = """INSERT INTO report (score_overall, ai_comments_overall, score_education, ai_comments_education, score_skills, ai_comments_skills, score_experience, ai_comments_experience, score_language, ai_comments_language, score_others, ai_comments_others, ai_confidence_level, candidate_id) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"""

        vals = (
            scores['overall'], comments['overall'], 
            scores['education'], comments['education'], 
            scores['skills'], comments['skills'], 
            scores['experience'], comments['experience'], 
            scores['language'], comments['language'], 
            scores['others'], comments['others'], 
            confidence, candidate_id
        )
        
        cursor.execute(sql, vals)
        conn.commit()
        
        update_progress(pid, 100)
        print(json.dumps({"status": "success", "score": scores['overall']}))

    except Exception as e:
        log_gemini_error(f"CRITICAL SCRIPT ERROR: {str(e)}")
        print(json.dumps({"status": "error", "message": str(e)}))
    finally:
        cursor.close()
        conn.close()

if __name__ == "__main__":
    pid = sys.argv[2] if len(sys.argv) > 2 else None
    if len(sys.argv) > 1:
        process_candidate(sys.argv[1], pid)