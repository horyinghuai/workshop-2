import sys
import json
import re
import PyPDF2
import docx
import spacy
import os

# --- PROGRESS FUNCTION ---
def update_progress(pid, percent):
    """Writes progress to a text file"""
    if pid:
        try:
            with open(f"progress_{pid}.txt", "w") as f:
                f.write(str(percent))
        except: pass

try:
    nlp = spacy.load('en_core_web_sm')
except OSError:
    nlp = None

# REMOVED: 'achievements' from this map
SECTION_MAP = {
    'objective': ('objective', 'summary', 'professional summary', 'profile'),
    'education': ('education', 'qualifications', 'academic background'),
    'skills': ('skills', 'technical skills', 'proficiencies'),
    'experience': ('experience', 'work experience', 'employment history', 'professional experience'),
    'language': ('languages', 'language proficiency'),
    'address': ('address', 'location', 'contact information') 
}

def extract_text_from_pdf(file_path):
    text = ""
    try:
        with open(file_path, 'rb') as f:
            reader = PyPDF2.PdfReader(f)
            for page in reader.pages:
                text += page.extract_text() or ""
    except Exception as e:
        return f"Error reading PDF: {e}"
    return text

def extract_text_from_docx(file_path):
    text = ""
    try:
        doc = docx.Document(file_path)
        for para in doc.paragraphs:
            text += para.text + "\n"
    except Exception as e:
        return f"Error reading DOCX: {e}"
    return text

def process_name(name_text):
    if name_text is None: return None
    name_text = re.sub(r'([A-Z])\s+([A-Z])', r'\1\2', name_text)
    name_text = re.sub(r'\s+', ' ', name_text).strip().title()
    return name_text

def extract_details(text, pid=None):
    if nlp is None:
         return {"error": "SpaCy model not found."}
    
    update_progress(pid, 20) # Analyzing Text
    
    raw_sections = {}
    final_data = {}
    others_text = text

    # 1. Extract Entities
    email_match = re.search(r'[\w\.-]+@[\w\.-]+\.\w+', text)
    phone_match = re.search(r'(\(?\+?\d{1,3}\)?[\s\.-]?)?\(?\d{3}\)?[\s\.-]?\d{3}[\s\.-]?\d{4}', text)
    
    if email_match:
        final_data['email'] = email_match.group(0)
        others_text = others_text.replace(email_match.group(0), "")
    if phone_match:
        final_data['contact_number'] = phone_match.group(0)
        others_text = others_text.replace(phone_match.group(0), "")

    update_progress(pid, 30) # Extracting Name

    name = None
    doc_for_name = nlp("\n".join(text.split('\n')[:5]))
    for ent in doc_for_name.ents:
        if ent.label_ == "PERSON":
            name = ent.text
            break
    
    if not name and email_match:
        local_part = email_match.group(0).split('@')[0]
        name = " ".join(re.split(r'[\._-]', local_part))

    final_data['name'] = process_name(name)
    if name: others_text = others_text.replace(name, "")

    # 2. Extract Sections
    update_progress(pid, 50) # Identifying Sections

    lower_text = text.lower()
    found_sections = []

    for section_name, keywords in SECTION_MAP.items():
        for keyword in keywords:
            start_index = lower_text.find(keyword)
            if start_index != -1:
                found_sections.append((start_index, section_name, len(keyword)))
                break 
    
    found_sections.sort(key=lambda x: x[0])

    update_progress(pid, 70) # Parsing Sections

    for i, (start, name, length) in enumerate(found_sections):
        content_start = start + length
        content_end = len(text)
        if i + 1 < len(found_sections):
            content_end = found_sections[i+1][0]
            
        raw_content = text[content_start:content_end].strip(": \n")
        raw_sections[name] = raw_content
        others_text = others_text.replace(raw_content, "")

    # 3. Assign
    final_data['objective'] = raw_sections.get('objective')
    final_data['education'] = raw_sections.get('education')
    final_data['skills'] = raw_sections.get('skills')
    final_data['experience'] = raw_sections.get('experience')
    # REMOVED: achievements assignment
    final_data['language'] = raw_sections.get('language')
    final_data['address'] = raw_sections.get('address')
    final_data['others'] = others_text.strip()
    final_data["full_text"] = text 
    
    # Fill missing
    all_keys = ["name", "email", "contact_number", "address", "objective", "education", "skills", "experience", "language", "others", "full_text"]
    for key in all_keys:
        if key not in final_data: final_data[key] = None

    update_progress(pid, 90) # Finalizing
    return final_data

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No file path."}))
        sys.exit(1)

    file_path = sys.argv[1]
    pid = sys.argv[2] if len(sys.argv) > 2 else None
    
    update_progress(pid, 5) # Started

    file_ext = file_path.split('.')[-1].lower()
    full_text = ""
    
    if file_ext == 'pdf':
        full_text = extract_text_from_pdf(file_path)
    elif file_ext == 'docx':
        full_text = extract_text_from_docx(file_path)
    else:
        print(json.dumps({"error": "Unsupported file type."}))
        sys.exit(1)

    update_progress(pid, 10) # File Read

    if full_text.startswith("Error"):
        print(json.dumps({"error": full_text}))
        sys.exit(1)

    extracted_data = extract_details(full_text, pid)
    
    update_progress(pid, 100) # Done
    print(json.dumps(extracted_data, indent=4))