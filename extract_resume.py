import sys
import json
import re
import PyPDF2
import docx
import os
import requests
import time
from dotenv import load_dotenv

# --- LOAD ENV VARIABLES ---
load_dotenv()

# --- CONFIGURATION ---
GEMINI_API_KEY = os.getenv('GEMINI_API_KEY', '')
GEMINI_BASE_URL = "https://generativelanguage.googleapis.com/v1beta/models/"
VALID_MODELS = ["gemini-2.0-flash", "gemini-1.5-flash", "gemini-1.5-pro"]

# --- UTILS: PROGRESS & FILE READING ---
def update_progress(pid, percent):
    if pid:
        try:
            with open(f"progress_{pid}.txt", "w") as f:
                f.write(str(percent))
        except: pass

def extract_text_from_pdf(file_path):
    text = ""
    try:
        with open(file_path, 'rb') as f:
            reader = PyPDF2.PdfReader(f)
            for page in reader.pages:
                text += page.extract_text() or ""
    except Exception as e:
        return ""
    return text

def extract_text_from_docx(file_path):
    text = ""
    try:
        doc = docx.Document(file_path)
        for para in doc.paragraphs:
            text += para.text + "\n"
    except Exception as e:
        return ""
    return text

# --- LOCAL NLP / REGEX FUNCTIONS ---
def extract_local_data(text):
    """
    Uses local regex to quickly extract and normalize specific fields 
    before sending to API. This saves tokens and ensures accuracy for patterns.
    """
    data = {}

    # 1. Local Email Extraction (High Accuracy Regex)
    email_match = re.search(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}', text)
    data['email'] = email_match.group(0) if email_match else None

    # 2. Local Phone Extraction & Normalization (Digits Only)
    # Find potential phone numbers (simple pattern for 9-15 digits)
    phone_match = re.search(r'(\+?\(?\d{1,4}\)?[\s\.-]?)?(\d{3}[\s\.-]?\d{3}[\s\.-]?\d{4})', text)
    if phone_match:
        raw_number = phone_match.group(0)
        # NORMALIZE LOCALLY: Remove all non-digit characters
        data['contact_number'] = re.sub(r'\D', '', raw_number)
    else:
        data['contact_number'] = None

    return data

# --- API FUNCTION ---
def call_gemini(prompt):
    headers = {'Content-Type': 'application/json'}
    payload = {
        "contents": [{"parts": [{"text": prompt}]}],
        "generationConfig": {"response_mime_type": "application/json"},
        "safetySettings": [
            {"category": "HARM_CATEGORY_HARASSMENT", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_HATE_SPEECH", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_SEXUALLY_EXPLICIT", "threshold": "BLOCK_NONE"},
            {"category": "HARM_CATEGORY_DANGEROUS_CONTENT", "threshold": "BLOCK_NONE"}
        ]
    }
    
    # Disable SSL warning for local dev environments
    import urllib3
    urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)

    for model in VALID_MODELS:
        url = f"{GEMINI_BASE_URL}{model}:generateContent?key={GEMINI_API_KEY}"
        try:
            response = requests.post(url, headers=headers, json=payload, timeout=30, verify=False)
            if response.status_code == 200:
                result = response.json()
                if 'candidates' in result and len(result['candidates']) > 0:
                    return result['candidates'][0]['content']['parts'][0]['text'].strip()
        except:
            continue
    return None

# --- MAIN LOGIC ---
def process_resume(file_path, pid=None):
    update_progress(pid, 10)

    # 1. READ FILE (Local Code)
    file_ext = file_path.split('.')[-1].lower()
    if file_ext == 'pdf':
        full_text = extract_text_from_pdf(file_path)
    elif file_ext == 'docx':
        full_text = extract_text_from_docx(file_path)
    else:
        return {"error": "Unsupported file type"}

    if not full_text:
        return {"error": "Could not extract text from file"}

    update_progress(pid, 30)

    # 2. EXTRACT STRUCTURED DATA LOCALLY (Local Code)
    # We grab email and phone locally because regex is faster and extremely reliable for patterns.
    local_data = extract_local_data(full_text)

    update_progress(pid, 50)

    # 3. EXTRACT UNSTRUCTURED DATA VIA API
    # We ask the API to handle the "messy" parts: Summarizing experience, formatting education, etc.
    clean_text = re.sub(r'\s+', ' ', full_text).strip()[:30000] 

    prompt = f"""
    You are a Resume Parser. Extract and normalize data from the resume text.
    
    Resume Text: "{clean_text}"

    Instructions:
    1. "name": Extract full name.
    2. "gender": Infer Male/Female/Unknown.
    3. "address": Extract city, state, country OR full address.
    4. "education": List as "Course, University, Grade, Year". Newline for multiple.
    5. "skills": List key skills (technical & soft), comma-separated.
    6. "experience": Summarize into short paragraphs/bullet points. Group by role.
    7. "language": List languages and proficiency.
    8. "others": Summarize awards/certs. Return null if empty/irrelevant.
    9. "objective": Short summary.

    Return strictly valid JSON.
    """

    api_response = call_gemini(prompt)
    
    update_progress(pid, 80)

    final_data = {
        "name": None, "gender": None, "email": None, "contact_number": None, 
        "address": None, "education": None, "skills": None, "experience": None, 
        "language": None, "others": None, "objective": None, "full_text": full_text
    }

    # 4. MERGE DATA (Hybrid Approach)
    if api_response:
        try:
            # Parse API JSON
            json_str = api_response.replace("```json", "").replace("```", "").strip()
            api_data = json.loads(json_str)
            
            # Merge API data into final_data
            for key in final_data:
                if key in api_data:
                    final_data[key] = api_data[key]

        except json.JSONDecodeError:
            final_data['others'] = "Error parsing AI response"

    # 5. OVERRIDE WITH LOCAL DATA (Best of both worlds)
    # If local regex found an email/phone, prioritize it (usually more accurate formatting)
    # If local regex failed but API found it, keep API version.
    if local_data['email']:
        final_data['email'] = local_data['email']
    
    if local_data['contact_number']:
        final_data['contact_number'] = local_data['contact_number']

    update_progress(pid, 100)
    return final_data

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No file path provided"}))
        sys.exit(1)

    path = sys.argv[1]
    proc_id = sys.argv[2] if len(sys.argv) > 2 else None

    # Check for API Key
    if not GEMINI_API_KEY:
        print(json.dumps({"error": "GEMINI_API_KEY missing in .env"}))
        sys.exit(1)

    result = process_resume(path, proc_id)
    print(json.dumps(result, indent=4))