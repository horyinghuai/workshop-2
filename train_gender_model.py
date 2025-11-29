import pandas as pd
import kagglehub
from kagglehub import KaggleDatasetAdapter
from sklearn.feature_extraction.text import CountVectorizer
from sklearn.naive_bayes import MultinomialNB
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score
import pickle
import os

# --- INSTALLATION INSTRUCTIONS ---
# pip install pandas scikit-learn kagglehub pyarrow fastparquet

def load_and_standardize_data():
    all_data = []

    print("--- Loading Datasets ---")

    # 1. Taktakhi Dataset (Kaggle)
    try:
        print("Loading taktakhi/names-for-gender-prediction...")
        df1 = kagglehub.load_dataset(KaggleDatasetAdapter.PANDAS, "taktakhi/names-for-gender-prediction", "labeled_names.csv")
        # Expected cols: 'name', 'gender' (M/F)
        df1.columns = df1.columns.str.lower()
        if 'name' in df1.columns and 'gender' in df1.columns:
            df1 = df1[['name', 'gender']]
            all_data.append(df1)
    except Exception as e:
        print(f"Skipped Taktakhi: {e}")

    # 2. Indian Names (Kaggle)
    try:
        print("Loading shubhamuttam/indian-names-by-gender...")
        # Note: File name might vary, loading generic pandas adapter usually grabs the CSV
        df2 = kagglehub.load_dataset(KaggleDatasetAdapter.PANDAS, "shubhamuttam/indian-names-by-gender", "Indian-Male-Names.csv") 
        # This dataset is split into files usually, let's try to load the main one or handle splits if kagglehub returns dict
        # Assuming the adapter handles it or returns the primary csv. 
        # If it returns a specific file, we normalize.
        df2.columns = df2.columns.str.lower()
        # Normalization might be needed (e.g. 'name', 'gender')
        if 'name' in df2.columns and 'gender' in df2.columns:
            all_data.append(df2[['name', 'gender']])
    except Exception as e:
        print(f"Skipped Indian Names: {e}")

    # 3. Muslim Names (Kaggle)
    try:
        print("Loading abuhuzaifahbidin/muslim-names...")
        df3 = kagglehub.load_dataset(KaggleDatasetAdapter.PANDAS, "abuhuzaifahbidin/muslim-names", "muslim_names.csv")
        df3.columns = df3.columns.str.lower()
        if 'name' in df3.columns and 'gender' in df3.columns:
            all_data.append(df3[['name', 'gender']])
    except Exception as e:
        print(f"Skipped Muslim Names: {e}")

    # 4. Hugging Face Dataset
    try:
        print("Loading erickrribeiro/gender-by-name (Hugging Face)...")
        splits = {'train': 'data/train-00000-of-00001-26e039960c54bfb0.parquet'}
        df4 = pd.read_parquet("hf://datasets/erickrribeiro/gender-by-name/" + splits["train"])
        df4.columns = df4.columns.str.lower()
        if 'name' in df4.columns and 'gender' in df4.columns:
            all_data.append(df4[['name', 'gender']])
    except Exception as e:
        print(f"Skipped Hugging Face: {e}")

    if not all_data:
        print("Error: No datasets loaded.")
        return pd.DataFrame()

    print("--- Merging Data ---")
    full_df = pd.concat(all_data, ignore_index=True)
    
    # Clean Data
    print("--- Cleaning Data ---")
    full_df.dropna(inplace=True)
    full_df['name'] = full_df['name'].astype(str).str.strip().str.lower()
    full_df['gender'] = full_df['gender'].astype(str).str.strip().str.upper()
    
    # Normalize Labels to 'Male' / 'Female'
    # Common variations: M, F, male, female, boy, girl
    gender_map = {
        'M': 'Male', 'MALE': 'Male', 'BOY': 'Male',
        'F': 'Female', 'FEMALE': 'Female', 'GIRL': 'Female'
    }
    full_df['gender'] = full_df['gender'].map(gender_map)
    full_df.dropna(subset=['gender'], inplace=True)
    
    full_df.drop_duplicates(subset=['name'], keep='first', inplace=True)
    
    print(f"Total unique names: {len(full_df)}")
    print(full_df['gender'].value_counts())
    
    return full_df

def train_model(df):
    if df.empty:
        return

    # Features: Character N-Grams are best for names (e.g. 'ary', 'nna' usually indicate gender)
    # Using First Name only logic usually
    
    X = df['name']
    y = df['gender']

    X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

    print("--- Training Model ---")
    # char_wb analyzer creates character n-grams inside word boundaries
    vectorizer = CountVectorizer(analyzer='char_wb', ngram_range=(2, 4))
    X_train_vec = vectorizer.fit_transform(X_train)
    X_test_vec = vectorizer.transform(X_test)

    clf = MultinomialNB()
    clf.fit(X_train_vec, y_train)

    preds = clf.predict(X_test_vec)
    acc = accuracy_score(y_test, preds)
    print(f"Model Accuracy: {acc * 100:.2f}%")

    # Save to disk
    print("--- Saving Model ---")
    with open('gender_vectorizer.pkl', 'wb') as f:
        pickle.dump(vectorizer, f)
    
    with open('gender_model.pkl', 'wb') as f:
        pickle.dump(clf, f)
        
    print("Done! Files 'gender_vectorizer.pkl' and 'gender_model.pkl' created.")

if __name__ == "__main__":
    data = load_and_standardize_data()
    train_model(data)