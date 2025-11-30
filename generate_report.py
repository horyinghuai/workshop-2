import sys
import mysql.connector
import json
import pandas as pd
import os
import requests
import time
import re
import warnings
import math

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
print("LOADED: generate_report.py (Updated Scoring Logic)")

# --- CONFIGURATION ---
DB_CONFIG = {
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'host': os.getenv('DB_HOST', 'localhost'),
    'database': os.getenv('DB_NAME', 'resume_reader')
}

# --- GEMINI API CONFIGURATION ---
GEMINI_API_KEY = os.getenv('GEMINI_API_KEY', '')
# Base URL for generation
GEMINI_BASE_URL = "https://generativelanguage.googleapis.com/v1beta/models/"

# Priority Models
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

# --- HELPER: CALL GEMINI API ---
def call_gemini(prompt, retries=3):
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

    for model in VALID_MODELS:
        url = f"{GEMINI_BASE_URL}{model}:generateContent?key={GEMINI_API_KEY}"
        for attempt in range(retries):
            try:
                response = requests.post(url, headers=headers, json=payload, timeout=20, verify=False)
                if response.status_code == 200:
                    result = response.json()
                    if 'candidates' in result and len(result['candidates']) > 0:
                        return result['candidates'][0]['content']['parts'][0]['text'].strip()
                elif response.status_code == 429:
                    time.sleep(2) # Rate limit backoff
                else:
                    break # Try next model on other errors
            except Exception as e:
                log_gemini_error(f"API Error ({model}): {str(e)}")
                time.sleep(1)
    return None

# --- SCORING: EDUCATION ---
def calculate_education_score(candidate_text, job_requirement_text):
    # 1. Check for empty content
    if not candidate_text or len(str(candidate_text).strip()) < 3:
        return 50.0

    c_text = str(candidate_text).lower()
    j_text = str(job_requirement_text).lower()

    # If no requirement specified, assume perfect fit
    if not j_text or len(j_text) < 3:
        return 90.0

    # 2. Hierarchy Logic
    edu_levels = {
        'phd': 5, 'doctorate': 5, 
        'master': 4, 
        'bachelor': 3, 'degree': 3, 
        'diploma': 2, 
        'certificate': 1, 'spm': 1
    }
    
    cand_level = 0
    req_level = 0
    
    for edu, val in edu_levels.items():
        if edu in c_text and val > cand_level: cand_level = val
        if edu in j_text and val > req_level: req_level = val
    
    # 3. Determine Base Score based on Hierarchy
    base_score = 50.0

    if cand_level > req_level:
        # Overqualified
        if req_level == 3 and cand_level == 4: # Req Bachelor, Has Master
            base_score = 85.0
        elif req_level == 3 and cand_level == 5: # Req Bachelor, Has PhD
            base_score = 95.0
        else:
            base_score = 90.0 # General Overqualified
    elif cand_level == req_level:
        # Exact Level Match
        base_score = 80.0
    elif cand_level < req_level and cand_level > 0:
        # Underqualified
        base_score = 60.0 # Will be adjusted by field relevance
    else:
        # No level found / Mismatch
        base_score = 50.0

    # 4. API Analysis: Field Compatibility (e.g., Marketing vs CS)
    # prompt returns a modifier score 0.0 to 1.0
    prompt = (
        f"Job Requirement: {j_text}\n"
        f"Candidate Education: {c_text}\n"
        f"Task: Is the candidate's field of study relevant to the job requirement?\n"
        f"Examples: AI is relevant to CS (1.0). Marketing is NOT relevant to CS (0.0). "
        f"Output ONLY a number between 0.0 and 1.0 representing relevance."
    )
    
    ai_relevance_str = call_gemini(prompt)
    try:
        match = re.search(r"(\d+(\.\d+)?)", ai_relevance_str)
        field_relevance = float(match.group(1)) if match else 0.5
    except:
        field_relevance = 0.5 # Default neutral

    # 5. Final Calculation
    # If field is not relevant, punish, but floor at 50
    if field_relevance < 0.4:
        final_score = max(50.0, base_score - 20)
    else:
        # If relevant, add small boost if exact match
        final_score = base_score + (field_relevance * 5)
    
    return round(min(100.0, final_score), 2)

# --- SCORING: HYBRID (Skills, Experience, Language) ---
def calculate_hybrid_score(candidate_text, job_requirement_text, section_name):
    # 1. Check for Empty Content
    # "if theres no content inside, make it 50 marks."
    if not candidate_text or len(str(candidate_text).strip()) < 3:
        return 50.0

    c_text = str(candidate_text).lower()
    j_text = str(job_requirement_text).lower()

    # If no requirement, assume 80 (Good fit)
    if not j_text or len(j_text) < 3:
        return 80.0

    # --- A. Keyword Match (10%) ---
    # Split job requirements into keywords
    job_keywords = [k.strip() for k in re.split(r'[,|\n]', j_text) if len(k.strip()) > 2]
    total_keywords = len(job_keywords)
    match_count = 0
    
    if total_keywords > 0:
        for keyword in job_keywords:
            if keyword in c_text:
                match_count += 1
        keyword_ratio = (match_count / total_keywords)
    else:
        keyword_ratio = 1.0

    score_keywords = keyword_ratio * 100 # Out of 100 base
    
    # --- B. Semantic Match (20%) ---
    # Using TF-IDF
    try:
        documents = [j_text, c_text]
        tfidf = TfidfVectorizer(stop_words='english')
        tfidf_matrix = tfidf.fit_transform(documents)
        cosine_sim = cosine_similarity(tfidf_matrix[0:1], tfidf_matrix[1:2])
        score_semantic = cosine_sim[0][0] * 100
    except:
        score_semantic = 50.0 # Fallback

    # --- C. API Analysis (70%) ---
    # Handles "relevant alternatives" (Java vs Python)
    prompt = (
        f"Role: HR Recruiter.\n"
        f"Job Requirement ({section_name}): \"{j_text}\"\n"
        f"Candidate ({section_name}): \"{c_text}\"\n"
        f"Task: Rate the relevance of the candidate's {section_name} on a scale of 0 to 100.\n"
        f"Rules:\n"
        f"1. If they lack exact skills but have relevant alternatives (e.g. Java instead of Python), give a HIGH score.\n"
        f"2. Ignore unrelated skills (do not punish).\n"
        f"3. Output ONLY the number."
    )
    
    ai_str = call_gemini(prompt)
    try:
        match = re.search(r"(\d+)", ai_str)
        score_ai = float(match.group(1)) if match else 50.0
    except:
        score_ai = 50.0

    # --- D. Weighted Calculation ---
    # Formula: (Keyword * 10%) + (Semantic * 20%) + (AI * 70%)
    # This generates a "Raw Quality Score" (0-100)
    raw_quality_score = (score_keywords * 0.10) + (score_semantic * 0.20) + (score_ai * 0.70)

    # --- E. Final Mapping (50 - 100 Range) ---
    # "total score... will be 50 + ..."
    # We map the 0-100 raw quality to the 50-100 range.
    final_score = 50 + (raw_quality_score / 2)

    # --- F. Detail Bonus ---
    # "If the candidate wrote more than 50 words... +5 points"
    word_count = len(c_text.split())
    if word_count > 50:
        final_score += 5

    return round(min(100.0, final_score), 2)

# --- SCORING: OTHERS ---
def calculate_others_score(candidate_text, job_requirement_text):
    # "if theres no content inside, make it 0 marks."
    if not candidate_text or len(str(candidate_text).strip()) < 3:
        return 0.0
    
    # If content exists, calculate normally using Hybrid logic
    return calculate_hybrid_score(candidate_text, job_requirement_text, "others")

# --- GENERATE COMMENT ---
def generate_comment(field_name, candidate_text, job_requirement_text, score):
    if not candidate_text or len(str(candidate_text)) < 5: 
        return "No details provided for this section."

    prompt = (
        f"Role: HR Recruiter. \n"
        f"Task: Write a 1-sentence comment comparing Candidate vs Job for '{field_name}'.\n"
        f"Job: {job_requirement_text}\n"
        f"Candidate: {candidate_text}\n"
        f"Score: {score}/100.\n"
        f"Context: If score > 80, praise. If score 50-70, mention they meet basics or have relevant alternatives. If < 50, mention gaps."
    )
    
    comment = call_gemini(prompt)
    return comment if comment else "Analysis unavailable."

# --- CONFIDENCE CALCULATION ---
def calculate_confidence(row):
    confidence = 0.0
    weights = {'experience': 35, 'education': 20, 'skills': 20, 'language': 10, 'others': 10}
    
    sections_present = 0
    for field, points in weights.items():
        content = str(row.get(field, ""))
        if content.lower() not in ['none', 'n/a', ''] and len(content) > 5:
            confidence += points
            sections_present += 1
            
    if sections_present < 3: confidence -= 15
    return round(min(99.0, max(10.0, confidence)), 2)

# --- MAIN PROCESS ---
def process_candidate(candidate_id, pid=None):
    update_progress(pid, 5)
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    
    try:
        # 1. Fetch Candidate
        cursor.execute("SELECT * FROM candidate WHERE candidate_id = %s", (candidate_id,))
        candidate = cursor.fetchone()
        if not candidate: return

        # 2. Fetch Job
        job_id = candidate['job_id']
        cursor.execute("SELECT * FROM job_position WHERE job_id = %s", (job_id,))
        job_reqs = cursor.fetchone()
        if not job_reqs: job_reqs = {} # Safety

        update_progress(pid, 20)

        # 3. Calculate Scores
        scores = {}
        comments = {}

        # A. Education (Special Logic)
        scores['education'] = calculate_education_score(candidate.get('education'), job_reqs.get('education'))
        comments['education'] = generate_comment('Education', candidate.get('education'), job_reqs.get('education'), scores['education'])
        update_progress(pid, 40)

        # B. Hybrid Sections
        for field in ['skills', 'experience', 'language']:
            scores[field] = calculate_hybrid_score(candidate.get(field), job_reqs.get(field), field)
            comments[field] = generate_comment(field, candidate.get(field), job_reqs.get(field), scores[field])
            time.sleep(1) # Prevent rate limit
        update_progress(pid, 70)

        # C. Others (0 if empty)
        scores['others'] = calculate_others_score(candidate.get('others'), job_reqs.get('others'))
        comments['others'] = generate_comment('Others', candidate.get('others'), job_reqs.get('others'), scores['others'])
        update_progress(pid, 85)

        # 4. Overall Score Logic
        # "Average of 5 distinct scores if OTHERS is NOT NULL (score > 0)"
        # "Average of 4 distinct scores if OTHERS is NULL (score == 0) 
        if scores['others'] > 0:
            total_sum = scores['education'] + scores['skills'] + scores['experience'] + scores['language'] + scores['others']
            overall_score = total_sum / 5
        else:
            total_sum = scores['education'] + scores['skills'] + scores['experience'] + scores['language']
            overall_score = total_sum / 4

        overall_score = round(overall_score, 2)
        
        # Overall Comment
        job_title = job_reqs.get('job_name', 'the role')
        comments['overall'] = generate_comment(f"Overall Fit for {job_title}", "See sections", "See sections", overall_score)

        confidence = calculate_confidence(candidate)

        # 5. Database Update
        cursor.execute("SELECT report_id FROM report WHERE candidate_id = %s", (candidate_id,))
        exists = cursor.fetchone()

        if exists:
            sql = """UPDATE report SET score_overall=%s, ai_comments_overall=%s, score_education=%s, ai_comments_education=%s, score_skills=%s, ai_comments_skills=%s, score_experience=%s, ai_comments_experience=%s, score_language=%s, ai_comments_language=%s, score_others=%s, ai_comments_others=%s, ai_confidence_level=%s WHERE candidate_id=%s"""
        else:
            sql = """INSERT INTO report (score_overall, ai_comments_overall, score_education, ai_comments_education, score_skills, ai_comments_skills, score_experience, ai_comments_experience, score_language, ai_comments_language, score_others, ai_comments_others, ai_confidence_level, candidate_id) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"""

        vals = (
            overall_score, comments['overall'], 
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
        print(json.dumps({"status": "success", "score": overall_score}))

    except Exception as e:
        log_gemini_error(f"CRITICAL ERROR: {str(e)}")
        print(json.dumps({"status": "error", "message": str(e)}))
    finally:
        if cursor: cursor.close()
        if conn: conn.close()

if __name__ == "__main__":
    pid = sys.argv[2] if len(sys.argv) > 2 else None
    if len(sys.argv) > 1:
        process_candidate(sys.argv[1], pid)