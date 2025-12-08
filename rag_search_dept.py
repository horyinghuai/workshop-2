import os
import json
import numpy as np
import mysql.connector
import asyncio
from dotenv import load_dotenv
from google import genai

# Load environment variables
load_dotenv()
DB_HOST = os.getenv("DB_HOST")
DB_USER = os.getenv("DB_USER")
DB_PASSWORD = os.getenv("DB_PASSWORD")
DB_NAME = os.getenv("DB_NAME")
GEMINI_KEY = os.getenv("GEMINI_API_KEY")

# Initialize GenAI client
client = genai.Client(api_key=GEMINI_KEY)

# Connect to database
conn = mysql.connector.connect(
    host=DB_HOST,
    user=DB_USER,
    password=DB_PASSWORD,
    database=DB_NAME
)
cursor = conn.cursor(dictionary=True)

# Load embeddings from DB
cursor.execute("SELECT department_id, embedding FROM department WHERE embedding IS NOT NULL")
rows = cursor.fetchall()

STORED_VECTORS = []
for row in rows:
    try:
        vector = np.array(json.loads(row['embedding']))
        STORED_VECTORS.append({"department_id": row['department_id'], "vector": vector})
    except:
        continue

# Cosine similarity function
def cosine_similarity(vec_a, vec_b):
    dot_product = np.dot(vec_a, vec_b)
    norm_a = np.linalg.norm(vec_a)
    norm_b = np.linalg.norm(vec_b)
    if norm_a == 0 or norm_b == 0:
        return 0
    return dot_product / (norm_a * norm_b)

# RAG search function
async def search_dept_rag(user_query, top_k=5):
    response = client.models.embed_content(
        model="text-embedding-004",
        contents=user_query
    )
    query_vector = np.array(response.embeddings[0].values)

    results = []
    # UPDATED: Lowered threshold to 0.35
    MIN_SCORE_THRESHOLD = 0.5

    for item in STORED_VECTORS:
        similarity = cosine_similarity(query_vector, item['vector'])
        
        if similarity >= MIN_SCORE_THRESHOLD:
            results.append({"department_id": item['department_id'], "score": similarity})
    
    results.sort(key=lambda x: x['score'], reverse=True)
    return [r['department_id'] for r in results[:top_k]]

# Main execution
if __name__ == "__main__":
    import sys
    if len(sys.argv) < 2:
        print("Usage: python rag_search_dept.py 'your query'")
        sys.exit(1)

    user_query = sys.argv[1]

    try:
        matching_ids = asyncio.run(search_dept_rag(user_query))
        print(json.dumps({"success": True, "ids": matching_ids}))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}))
        sys.exit(1)