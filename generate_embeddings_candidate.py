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
cursor.execute("SELECT * FROM candidate WHERE embedding IS NULL")
candidates = cursor.fetchall()

for cand in candidates:
    # Combine textual fields
    # Adjust fields based on your actual table columns
    cand_text = f"""
    Name: {cand.get('name', '')}
    Job Position: {cand.get('applied_job_position', '')}
    Department: {cand.get('department', '')}
    Skills: {cand.get('skills', '')}
    Education: {cand.get('education', '')}
    Experience: {cand.get('experience', '')}
    Objective: {cand.get('objective', '')}
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