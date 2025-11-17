import sys
import json
import re
import PyPDF2
import docx
import spacy  # --- Import SpaCy (Still used for NER Name) ---

# -----------------------------------------------------------------
# --- IMPORTANT: One-Time Setup (run this in your terminal) ---
#
# 1. pip install spacy PyPDF2 python-docx
# 2. python -m spacy download en_core_web_sm
#
# -----------------------------------------------------------------

try:
    nlp = spacy.load('en_core_web_sm')
except OSError:
    # This will be caught and sent as a JSON error if the model isn't downloaded
    nlp = None

# --- NEW: More robust section keywords ---
SECTION_MAP = {
    'objective': ('objective', 'summary', 'professional summary', 'profile'),
    'education': ('education', 'qualifications', 'academic background'),
    'skills': ('skills', 'technical skills', 'proficiencies'),
    'experience': ('experience', 'work experience', 'employment history', 'professional experience'),
    'achievements': ('achievements', 'awards', 'honors', 'accomplishments'),
    'language': ('languages', 'language proficiency'),
    'address': ('address', 'location', 'contact information') 
    # 'contact information' might also contain phone/email, but NER handles those first.
}

def extract_text_from_pdf(file_path):
    """Extracts text from a PDF file."""
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
    """Extracts text from a DOCX file."""
    text = ""
    try:
        doc = docx.Document(file_path)
        for para in doc.paragraphs:
            text += para.text + "\n"
    except Exception as e:
        return f"Error reading DOCX: {e}"
    return text


# --- REMOVED: The 'clean_and_summarize' function is no longer needed ---


def process_name(name_text):
    """
    Cleans extracted name text based on user requirements.
    - 'F A R A H M A R T I N' -> 'Farah Martin'
    - 'john doe' -> 'John Doe'
    - 'Hor Ying Huai' -> 'Hor Ying Huai'
    """
    if name_text is None:
        return None
    
    # Fix names with extra spaces (F A R A H -> FARAH)
    name_text = re.sub(r'([A-Z])\s+([A-Z])', r'\1\2', name_text)
    # Fix names that were spaced (F A R A H M A R T I N -> FARAH MARTIN)
    name_text = re.sub(r'([A-Z])\s+([A-Z])', r'\1\2', name_text)
    
    # Standardize remaining whitespace and apply Title Case
    name_text = re.sub(r'\s+', ' ', name_text).strip().title()
    return name_text

def extract_details(text):
    """Extracts details using regex, keywords, and NLP."""
    
    if nlp is None:
         return {"error": "SpaCy model 'en_core_web_sm' not found. Run: python -m spacy download en_core_web_sm"}

    # This will hold the raw, uncleaned text for each section
    raw_sections = {}
    # This will hold the final, cleaned data
    final_data = {}
    
    # This text will have sections removed as we find them
    others_text = text

    # --- 1. Extract Entities (Name, Email, Phone) ---
    
    # Email and Phone (Regex)
    email_match = re.search(r'[\w\.-]+@[\w\.-]+\.\w+', text)
    phone_match = re.search(r'(\(?\+?\d{1,3}\)?[\s\.-]?)?\(?\d{3}\)?[\s\.-]?\d{3}[\s\.-]?\d{4}', text)
    
    if email_match:
        final_data['email'] = email_match.group(0)
        others_text = others_text.replace(email_match.group(0), "")
        
    if phone_match:
        final_data['contact_number'] = phone_match.group(0)
        others_text = others_text.replace(phone_match.group(0), "")

    # Name (NLP NER)
    name = None
    doc_for_name = nlp("\n".join(text.split('\n')[:5])) # Check first 5 lines
    for ent in doc_for_name.ents:
        if ent.label_ == "PERSON":
            name = ent.text
            break
    
    # Fallback if NER fails
    if not name and email_match:
        local_part = email_match.group(0).split('@')[0]
        name = " ".join(re.split(r'[\._-]', local_part))

    # Process and store the name
    final_data['name'] = process_name(name)
    if name: # Remove raw name from others_text
        others_text = others_text.replace(name, "")


    # --- 2. Extract Text Sections (New Robust Logic) ---
    
    lower_text = text.lower()
    found_sections = [] # (start_index, section_name, keyword_length)

    # Find the start index of all known sections
    for section_name, keywords in SECTION_MAP.items():
        for keyword in keywords:
            start_index = lower_text.find(keyword)
            if start_index != -1:
                found_sections.append((start_index, section_name, len(keyword)))
                # We only want the first match for each keyword *group*
                break 
    
    # Sort sections by their start index
    found_sections.sort(key=lambda x: x[0])

    # Extract content between sections
    for i, (start, name, length) in enumerate(found_sections):
        # The content starts after the keyword
        content_start = start + length
        
        # The content ends at the start of the next section
        content_end = len(text)
        if i + 1 < len(found_sections):
            content_end = found_sections[i+1][0]
            
        # Get the raw text content
        raw_content = text[content_start:content_end].strip(": \n")
        
        # Store raw content for "others" logic
        raw_sections[name] = raw_content
        
        # Remove this raw content from "others_text"
        others_text = others_text.replace(raw_content, "")

    # --- 3. Assign Raw Section Text ---
    # MODIFIED: No longer calls 'clean_and_summarize'. Assigns raw text.
    
    final_data['objective'] = raw_sections.get('objective')
    final_data['education'] = raw_sections.get('education')
    final_data['skills'] = raw_sections.get('skills')
    final_data['experience'] = raw_sections.get('experience')
    final_data['achievements'] = raw_sections.get('achievements')
    final_data['language'] = raw_sections.get('language')
    final_data['address'] = raw_sections.get('address')

    # --- 4. Process "Others" section ---
    # The 'others_text' has had all recognized sections removed.
    # We just strip extra whitespace from what is left.
    final_data['others'] = others_text.strip()
    
    # Add full text for debugging
    final_data["full_text"] = text 
    
    # Fill any missing keys with None
    all_keys = ["name", "email", "contact_number", "address", "objective", "education", "skills", "experience", "achievements", "language", "others", "full_text"]
    for key in all_keys:
        if key not in final_data:
            final_data[key] = None

    return final_data


if __name__ == "__main__":
    if nlp is None:
        print(json.dumps({"error": "SpaCy model 'en_core_web_sm' not found. Run: python -m spacy download en_core_web_sm"}))
        sys.exit(1)

    if len(sys.argv) < 2:
        print(json.dumps({"error": "No file path provided."}))
        sys.exit(1)

    file_path = sys.argv[1]
    file_ext = file_path.split('.')[-1].lower()

    full_text = ""
    if file_ext == 'pdf':
        full_text = extract_text_from_pdf(file_path)
    elif file_ext == 'docx':
        full_text = extract_text_from_docx(file_path)
    else:
        print(json.dumps({"error": "Unsupported file type."}))
        sys.exit(1)

    if full_text.startswith("Error"):
        print(json.dumps({"error": full_text}))
        sys.exit(1)

    # Extract details and print as JSON
    extracted_data = extract_details(full_text)
    
    print(json.dumps(extracted_data, indent=4))