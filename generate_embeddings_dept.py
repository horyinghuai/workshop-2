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

# --- Fetch departments with missing embeddings ---
cursor.execute("SELECT * FROM department WHERE embedding IS NULL")
departments = cursor.fetchall()

for dept in departments:
    # Combine textual fields
    dept_text = f"""
    Department: {dept['department_name']}. 
    Description: {dept['description'] or ''}.
    """
    try:
        # Generate embedding
        response = client.models.embed_content(
            model="text-embedding-004",
            contents=dept_text
        )
        vector = np.array(response.embeddings[0].values)
        vector_json = json.dumps(vector.tolist())

        # Store embedding in DB
        cursor.execute(
            "UPDATE department SET embedding=%s WHERE department_id=%s",
            (vector_json, dept['department_id'])
        )
        conn.commit()
        print(f"Embedding stored for department_id {dept['department_id']}")

    except Exception as e:
        print(f"Failed to embed department_id {dept['department_id']}: {e}")

conn.close()