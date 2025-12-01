{"id":"93145","variant":"standard","title":"Generate Embeddings Script with Debug"}
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
cursor.execute("SELECT * FROM job_position WHERE embedding IS NULL")
jobs = cursor.fetchall()

for job in jobs:
    # Combine all textual fields
    job_text = f"""
    {job['job_name']}. Description: {job['description'] or ''}.
    Skills: {job['skills'] or ''}.
    Education: {job['education'] or ''}.
    Experience: {job['experience'] or ''}.
    Language: {job['language'] or ''}.
    Others: {job['others'] or ''}
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
