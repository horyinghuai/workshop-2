import sys
import mysql.connector
import json
import pandas as pd
import kagglehub
import os
import requests
import time
import re
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

# --- CONFIGURATION ---
DB_CONFIG = {
    'user': 'root',
    'password': '',
    'host': 'localhost',
    'database': 'resume_reader'
}

HF_API_URL = "https://api-inference.huggingface.co/models/google/flan-t5-large"
# REPLACE WITH YOUR TOKEN
HF_API_TOKEN = "" 

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
    if not text or len(str(text)) < 5: return "No details provided for this section."

    headers = {"Authorization": f"Bearer {HF_API_TOKEN}"}
    
    prompt = (
        f"You are an HR Recruiter. Review the candidate's {field_name}: \"{text}\". "
        f"The calculated relevance score is {score}/100. "
        f"Write a detailed, professional evaluation of about 60 words explaining this score and the candidate's strengths."
    )
    
    payload = {
        "inputs": prompt, 
        "parameters": {
            "max_new_tokens": 250,
            "temperature": 0.7,
            "return_full_text": False,
            "wait_for_model": True
        }
    }
    
    for attempt in range(5): 
        try:
            response = requests.post(HF_API_URL, headers=headers, json=payload, timeout=60) 
            result = response.json()
            
            if isinstance(result, list) and 'generated_text' in result[0]:
                return result[0]['generated_text'].strip()
            elif isinstance(result, dict) and 'error' in result:
                err = result['error'].lower()
                if 'loading' in err:
                    time.sleep(20) 
                    continue
                else:
                    print(f"API Error (Attempt {attempt}): {err}")
                    time.sleep(2)
                    continue
        except Exception as e:
            time.sleep(2)

    word_count = len(text.split())
    quality_text = "excellent" if score > 80 else "sufficient"
    return (f"System generated assessment: The candidate provided {word_count} words regarding their {field_name}. "
            f"Based on keyword analysis, the proficiency level appears to be {quality_text} (Score: {score}). "
            f"Detailed AI commentary is currently unavailable due to connection limits.")

def calculate_confidence(row):
    # REMOVED: achievements
    fields = ['education', 'skills', 'experience']
    filled = sum(1 for f in fields if len(str(row.get(f, ""))) > 5)
    return round(min(98.5, 85.0 + (filled * 4.0)), 2)

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

        # REMOVED: achievements
        fields = ['education', 'skills', 'experience', 'language', 'others']
        scores = {}
        comments = {}
        
        step = 60 / len(fields)
        current_p = 30

        for field in fields:
            content = candidate.get(field, "")
            scores[field] = calculate_hybrid_score(content, field)
            comments[field] = generate_llm_comment(field, content, scores[field])
            
            time.sleep(2) 
            
            current_p += step
            update_progress(pid, int(current_p))

        scores['overall'] = sum(scores.values()) / len(scores)
        comments['overall'] = generate_llm_comment("Profile Summary", str(candidate), scores['overall'])
        confidence = calculate_confidence(candidate)

        update_progress(pid, 95)

        cursor.execute("SELECT report_id FROM report WHERE candidate_id = %s", (candidate_id,))
        exists = cursor.fetchone()

        # REMOVED: score_achievements, ai_comments_achievements
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
        print(json.dumps({"status": "error", "message": str(e)}))
    finally:
        cursor.close()
        conn.close()

if __name__ == "__main__":
    pid = sys.argv[2] if len(sys.argv) > 2 else None
    if len(sys.argv) > 1:
        process_candidate(sys.argv[1], pid)