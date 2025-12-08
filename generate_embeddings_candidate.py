import os
import json
import numpy as np
import mysql.connector
from dotenv import load_dotenv
from google import genai

# --- Load environment variables ---
load_dotenv()
DB_HOST = os.getenv("DB_HOST")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")
DB_NAME = os.getenv("DB_NAME")
GEMINI_KEY = os.getenv("GEMINI_API_KEY")

# --- Initialize GenAI client ---
client = genai.Client(api_key=GEMINI_KEY)

# --- Connect to database ---
conn = mysql.connector.connect(
    host=DB_HOST,
    user=DB_USER,
    password=DB_PASSWORD,
    database=DB_NAME
)
cursor = conn.cursor(dictionary=True)

# --- Fetch candidates with missing embeddings ---
# UPDATED: Added JOINs to get Job Name and Department Name
query = """
    SELECT c.*, jp.job_name, d.department_name
    FROM candidate c
    LEFT JOIN job_position jp ON c.job_id = jp.job_id
    LEFT JOIN department d ON jp.department_id = d.department_id
    WHERE c.embedding IS NULL
"""
cursor.execute(query)
candidates = cursor.fetchall()

for cand in candidates:
    # --- UPDATED: Attempt to read resume content from file ---
    resume_text = ""
    resume_path = cand.get('resume_formatted')
    
    if resume_path and os.path.isfile(resume_path):
        try:
            with open(resume_path, 'r', encoding='utf-8', errors='ignore') as f:
                # Read first 4000 chars to avoid context limits if necessary, 
                # though Gemini handles large context well.
                resume_text = f.read().strip()[:8000] 
        except Exception as e:
            print(f"Could not read resume file {resume_path}: {e}")
            resume_text = ""

    # --- UPDATED: Construct Richer Context ---
    cand_text = f"""
    Candidate Name: {cand.get('name', 'N/A')}
    Applied Job Position: {cand.get('job_name', 'N/A')}
    Department: {cand.get('department_name', 'N/A')}
    Skills: {cand.get('skills', '')}
    Education: {cand.get('education', '')}
    Experience: {cand.get('experience', '')}
    Objective: {cand.get('objective', '')}
    
    Resume Content:
    {resume_text}
    """
    
    try:
        # Generate embedding
        response = client.models.embed_content(
            model="text-embedding-004",
            contents=cand_text
        )
        vector = np.array(response.embeddings[0].values)
        vector_json = json.dumps(vector.tolist())

        # Store embedding in DB
        cursor.execute(
            "UPDATE candidate SET embedding=%s WHERE candidate_id=%s",
            (vector_json, cand['candidate_id'])
        )
        conn.commit()
        print(f"Embedding stored for candidate_id {cand['candidate_id']}")

    except Exception as e:
        print(f"Failed to embed candidate_id {cand['candidate_id']}: {e}")

conn.close()