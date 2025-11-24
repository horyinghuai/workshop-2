import sys
import mysql.connector
import json
import pandas as pd
import os
import requests
import time
import re
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

# --- DEBUG: CONFIRM NEW CODE IS RUNNING ---
print("LOADED: process_report.py (Gemini 1.5 Pro Version)")

# --- CONFIGURATION ---
DB_CONFIG = {
    'user': 'root',
    'password': '',
    'host': 'localhost',
    'database': 'resume_reader'
}

# --- GEMINI API CONFIGURATION ---
# Using Gemini 1.5 Pro as requested
GEMINI_API_KEY = "AIzaSyCinubaFIEIxXJ8EqqMHgd_uF74HNvknbw" 
GEMINI_URL = f"https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key={GEMINI_API_KEY}"

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

# REMOVED: achievements
KEYWORDS = {
    'education': ['phd', 'doctorate', 'master', 'mba', 'bachelor', 'degree', 'diploma', 'certificate', 'university', 'college', 'graduated'],
    'skills': ['python', 'java', 'sql', 'react', 'aws', 'excel', 'communication', 'leadership', 'analysis', 'management', 'c++', 'html', 'css'],
    'experience': ['manager', 'lead', 'senior', 'developed', 'managed', 'years', 'supervised', 'created', 'achieved'],
    'language': ['english', 'malay', 'mandarin', 'french', 'spanish', 'japanese', 'fluent'],
    'others': ['award', 'achievement', 'certified', 'winner', 'project', 'dean', 'volunteer', 'hobby']
}

def get_db_connection():
    return mysql.connector.connect(**DB_CONFIG)

def calculate_hybrid_score(text, field_name):
    if not text or len(str(text)) < 3: return 0.0
    text = str(text).lower()
    
    relevant_words = KEYWORDS.get(field_name, [])
    keyword_hits = sum(1 for word in relevant_words if word in text)
    keyword_score = min(100.0, (keyword_hits / 4) * 100)

    try:
        documents = [
            "Experienced software engineer python java sql agile leadership",
            "Project manager prince2 scrum master budget management",
            "Data scientist machine learning statistics python r",
            "Human resources recruitment onboarding employee relations",
            "Marketing specialist seo content strategy social media",
            "Fresh graduate computer science internship web development",
            "Bachelor of Computer Science University with Dean's List",
            "Fluent in English Malay and Mandarin"
        ]
        documents.append(text)
        tfidf = TfidfVectorizer(stop_words='english')
        tfidf_matrix = tfidf.fit_transform(documents)
        cosine_sim = cosine_similarity(tfidf_matrix[-1], tfidf_matrix[:-1])
        ml_score = min(100.0, (cosine_sim.max() / 0.3) * 100)
    except: ml_score = 50.0

    final_score = (keyword_score * 0.6) + (ml_score * 0.4)
    if len(text.split()) > 20: final_score += 5
    
    return round(min(99.0, max(10.0, final_score)), 2)

def generate_llm_comment(field_name, text, score):
    # 1. Validation Check
    if not text or len(str(text)) < 5: 
        return "No details provided for this section."

    # 2. Construct Payload with Safety Settings Disabled
    # (Fixes "Fallback" issues caused by personal data filters)
    prompt = (
        f"You are an HR Recruiter. Review the candidate's {field_name}: \"{text}\". "
        f"The calculated relevance score is {score}/100. "
        f"Write a professional evaluation (max 40 words) explaining this score."
    )

    payload = {
        "contents": [{
            "parts": [{"text": prompt}]
        }],
        "safetySettings": [
            {"category": "HARM_CATEGORY_HARASSMENT", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_HATE_SPEECH", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold": "BLOCK_NONE"}
        ]
    }
    
    headers = {'Content-Type': 'application/json'}

    # 3. Call Gemini API (Retries included)
    for attempt in range(3): 
        try:
            response = requests.post(GEMINI_URL, headers=headers, json=payload, timeout=30)
            
            if response.status_code == 200:
                result = response.json()
                # Extract text
                if 'candidates' in result and len(result['candidates']) > 0:
                    content = result['candidates'][0]['content']['parts'][0]['text']
                    return content.strip()
                else:
                    log_gemini_error(f"Field: {field_name} - Empty candidates. Response: {result}")
            else:
                # Log specific error code
                log_gemini_error(f"Field: {field_name} - HTTP {response.status_code}: {response.text}")
                time.sleep(1)
                
        except Exception as e:
            log_gemini_error(f"Field: {field_name} - Exception: {str(e)}")
            time.sleep(1)

    # 4. Fallback only if strictly necessary
    word_count = len(text.split())
    return (f"Candidate provided {word_count} words for {field_name}. "
            f"Proficiency score: {score}/100. (AI Service Unavailable - Check gemini_error_log.txt)")

# UPDATED: Dynamic Confidence Calculation
def calculate_confidence(row):
    confidence = 50.0 # Base confidence
    
    weights = {
        'experience': {'threshold': 20, 'points': 20}, 
        'education': {'threshold': 10, 'points': 10},
        'skills': {'threshold': 5, 'points': 10},
        'language': {'threshold': 2, 'points': 5},
        'others': {'threshold': 5, 'points': 4}
    }

    for field, criteria in weights.items():
        content = str(row.get(field, ""))
        if content.lower() == 'none' or content == "":
            word_count = 0
        else:
            word_count = len(content.split())
        
        if word_count >= criteria['threshold']:
            confidence += criteria['points']
        elif word_count > 0:
            confidence += (criteria['points'] / 2)

    return round(min(99.0, confidence), 2)

def process_candidate(candidate_id, pid=None):
    update_progress(pid, 5) 
    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    
    try:
        update_progress(pid, 10)
        cursor.execute("SELECT * FROM candidate WHERE candidate_id = %s", (candidate_id,))
        candidate = cursor.fetchone()
        if not candidate: return

        update_progress(pid, 30)
        
        try: import kagglehub
        except: pass

        fields = ['education', 'skills', 'experience', 'language', 'others']
        scores = {}
        comments = {}
        
        step = 60 / len(fields)
        current_p = 30

        for field in fields:
            content = candidate.get(field, "")
            scores[field] = calculate_hybrid_score(content, field)
            comments[field] = generate_llm_comment(field, content, scores[field])
            
            # Pro is slower than Flash, allow 2s pause to be safe
            time.sleep(2) 
            
            current_p += step
            update_progress(pid, int(current_p))

        scores['overall'] = sum(scores.values()) / len(scores)
        comments['overall'] = generate_llm_comment("Profile Summary", str(candidate), scores['overall'])
        confidence = calculate_confidence(candidate)

        update_progress(pid, 95)

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