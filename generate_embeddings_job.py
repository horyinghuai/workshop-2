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

# --- Fetch all jobs ---
# UPDATED: Added JOIN to get Department Name
query = """
    SELECT jp.*, d.department_name
    FROM job_position jp
    LEFT JOIN department d ON jp.department_id = d.department_id
    WHERE jp.embedding IS NULL
"""
cursor.execute(query)
jobs = cursor.fetchall()

for job in jobs:
    # --- UPDATED: Richer Context ---
    job_text = f"""
    Job Title: {job['job_name']}
    Department: {job.get('department_name', 'N/A')}
    Description: {job['description'] or ''}
    Required Skills: {job['skills'] or ''}
    Education Level: {job['education'] or ''}
    Experience Required: {job['experience'] or ''}
    Languages: {job['language'] or ''}
    Other Requirements: {job['others'] or ''}
    """
    try:
        # Generate embedding
        response = client.models.embed_content(
            model="text-embedding-004",
            contents=job_text
        )
        vector = np.array(response.embeddings[0].values)
        vector_json = json.dumps(vector.tolist())

        # Store embedding in DB
        cursor.execute(
            "UPDATE job_position SET embedding=%s WHERE job_id=%s",
            (vector_json, job['job_id'])
        )
        conn.commit()
        print(f"Embedding stored for job_id {job['job_id']} (vector length {len(vector)})")

    except Exception as e:
        print(f"Failed to embed job_id {job['job_id']}: {e}")

conn.close()