"""
predict.py - Script prediksi kategori pengaduan Meine Welt Kafe
Dipanggil dari PHP via shell_exec.

Usage:
    python predict.py "teks pengaduan di sini"

Output (JSON):
    {"success": true, "kategori": ["produk","harga"], "confidence": {...}, "primary": "produk"}
"""

import sys
import os
import json
import re
import warnings
warnings.filterwarnings('ignore')

# Pastikan output stdout adalah UTF-8 untuk Windows
if sys.stdout.encoding != 'utf-8':
    try:
        sys.stdout.reconfigure(encoding='utf-8')
    except Exception:
        pass


def main():
    try:
        if len(sys.argv) < 2:
            print(json.dumps({'success': False, 'error': 'No input text provided'}))
            return

        teks = sys.argv[1]

        # Import library
        import joblib
        from Sastrawi.Stemmer.StemmerFactory import StemmerFactory
        from Sastrawi.StopWordRemover.StopWordRemoverFactory import StopWordRemoverFactory

        # Lokasi script = lokasi model .pkl
        base_dir = os.path.dirname(os.path.abspath(__file__))

        model = joblib.load(os.path.join(base_dir, 'model_nb_binary_relevance.pkl'))
        vectorizer = joblib.load(os.path.join(base_dir, 'tfidf_vectorizer.pkl'))
        mlb = joblib.load(os.path.join(base_dir, 'mlb_transformer.pkl'))

        stemmer = StemmerFactory().create_stemmer()

        # ================================================================
        # KONFIGURASI PREPROCESSING (harus sama dengan notebook)
        # ================================================================
        NEGATION_WORDS = {
            'tidak', 'bukan', 'kurang', 'belum', 'jangan',
            'tanpa', 'tak', 'tiada', 'enggan'
        }

        stopword_factory = StopWordRemoverFactory()
        base_stopwords = set(stopword_factory.get_stop_words())
        final_stopwords = base_stopwords - NEGATION_WORDS

        SLANG_DICT = {
            'ga': 'tidak', 'gak': 'tidak', 'gk': 'tidak', 'ngga': 'tidak',
            'nggak': 'tidak', 'tdk': 'tidak', 'kagak': 'tidak', 'ndak': 'tidak',
            'kaga': 'tidak', 'gx': 'tidak', 'enggak': 'tidak', 'engga': 'tidak',
            'nda': 'tidak', 'nga': 'tidak', 'gaada': 'tidak ada',
            'yg': 'yang', 'dgn': 'dengan', 'utk': 'untuk', 'krn': 'karena',
            'tp': 'tapi', 'tpi': 'tapi',
            'sdh': 'sudah', 'udh': 'sudah', 'udah': 'sudah', 'uda': 'sudah',
            'blm': 'belum', 'blom': 'belum', 'blum': 'belum',
            'bgt': 'sangat', 'bgtt': 'sangat', 'bngt': 'sangat', 'banget': 'sangat',
            'bener': 'benar', 'bnr': 'benar',
            'gmn': 'bagaimana', 'gmna': 'bagaimana',
            'sm': 'sama', 'sma': 'sama',
            'org': 'orang', 'orng': 'orang',
            'lg': 'lagi', 'lgi': 'lagi',
            'aja': 'saja', 'aj': 'saja', 'doang': 'saja', 'doank': 'saja',
            'byk': 'banyak', 'bnyk': 'banyak',
            'jg': 'juga', 'jga': 'juga',
            'klo': 'kalau', 'kalo': 'kalau', 'klu': 'kalau',
            'sy': 'saya', 'gw': 'saya', 'gue': 'saya', 'ak': 'saya',
            'bs': 'bisa', 'bsa': 'bisa',
            'emg': 'memang', 'emng': 'memang', 'emang': 'memang',
            'skrg': 'sekarang', 'skrng': 'sekarang',
            'hrs': 'harus', 'hrus': 'harus',
            'trs': 'terus', 'trus': 'terus',
            'msh': 'masih', 'msih': 'masih',
            'dlu': 'dulu', 'dl': 'dulu',
            'bkn': 'bukan',
            'dr': 'dari', 'dri': 'dari',
            'tpt': 'tempat', 'tmpt': 'tempat',
            'smpe': 'sampai', 'sampe': 'sampai',
            'dtg': 'datang', 'dtng': 'datang',
            'plg': 'paling',
            'nyesel': 'menyesal', 'nysel': 'menyesal',
            'nunggu': 'menunggu', 'nungguin': 'menunggu',
            'mantul': 'mantap', 'mantab': 'mantap', 'mantep': 'mantap',
            'gercep': 'cepat',
            'recomended': 'rekomendasi', 'recommended': 'rekomendasi', 'rekomen': 'rekomendasi',
            'worthit': 'sepadan', 'worth': 'sepadan',
            'pricey': 'mahal', 'overprice': 'mahal', 'overpriced': 'mahal',
            'slow': 'lambat', 'fast': 'cepat',
            'tasty': 'enak', 'yummy': 'enak', 'delicious': 'enak',
            'fresh': 'segar',
            'zonk': 'mengecewakan', 'bad': 'buruk',
            'good': 'bagus', 'nice': 'bagus', 'great': 'bagus',
            'friendly': 'ramah', 'clean': 'bersih', 'dirty': 'kotor',
            'cozy': 'nyaman', 'comfortable': 'nyaman',
            'cheap': 'murah', 'expensive': 'mahal',
            'mkn': 'makanan', 'mknn': 'makanan',
            'mnm': 'minuman', 'mnmn': 'minuman',
            'nasgor': 'nasi goreng',
        }

        def fix_elongation(text):
            return re.sub(r'(.)\1{2,}', r'\1\1', text)

        def preprocess_text(text):
            text = str(text).lower().strip()
            text = re.sub(r'http\S+|www\S+', '', text)
            text = re.sub(r'[@#]\w+', '', text)
            text = re.sub(r'\d+', '', text)
            text = re.sub(r'[^a-z\s]', ' ', text)
            text = re.sub(r'\s+', ' ', text).strip()
            text = fix_elongation(text)

            tokens = text.split()
            tokens = [SLANG_DICT.get(t, t) for t in tokens]

            # Negasi merging
            merged = []
            skip = False
            for i, t in enumerate(tokens):
                if skip:
                    skip = False
                    continue
                if t in NEGATION_WORDS and i + 1 < len(tokens):
                    merged.append(f'{t}_{tokens[i+1]}')
                    skip = True
                else:
                    merged.append(t)
            tokens = merged

            tokens = [t for t in tokens if t not in final_stopwords or t in NEGATION_WORDS]
            tokens = [t for t in tokens if len(t) > 1]

            text_clean = ' '.join(tokens)
            return stemmer.stem(text_clean)

        def sentence_split(text):
            text = str(text).lower().strip()
            segments = re.split(r'[.!?]+', text)

            conjunctions = ['tetapi', 'namun', 'tapi', 'sedangkan', 'padahal']
            expanded = []
            for seg in segments:
                parts = [seg]
                for conj in conjunctions:
                    new_parts = []
                    for part in parts:
                        splits = re.split(rf'\s+{conj}\s+', part)
                        new_parts.extend(splits)
                    parts = new_parts
                expanded.extend(parts)

            result = []
            for seg in expanded:
                seg = seg.strip()
                if len(seg.split()) > 6:
                    sub = re.split(r'\s+dan\s+', seg)
                    result.extend(sub)
                else:
                    result.append(seg)

            result = [s.strip() for s in result if len(s.strip().split()) >= 2]
            return result if result else [text]

        # ================================================================
        # PREDIKSI DENGAN SENTENCE SPLITTING
        # ================================================================
        threshold = 0.3
        segmen = sentence_split(teks)
        preprocessed_segmen = [preprocess_text(s) for s in segmen]

        # Vectorize semua segmen sekaligus
        X_vec = vectorizer.transform(preprocessed_segmen)

        # Ambil probabilitas per kelas
        y_proba = model.predict_proba(X_vec)  # shape: (n_segmen, n_kelas)

        # Agregasi: ambil max probability antar segmen
        max_proba = y_proba.max(axis=0)

        # Filter berdasarkan threshold
        predicted_labels = []
        confidence_dict = {}
        for i, cls in enumerate(mlb.classes_):
            conf = float(max_proba[i])
            confidence_dict[cls] = round(conf, 4)
            if conf >= threshold:
                predicted_labels.append(cls)

        # Kalau tidak ada yang lewat threshold, ambil yang probabilitasnya tertinggi
        if not predicted_labels:
            best_idx = int(max_proba.argmax())
            predicted_labels.append(str(mlb.classes_[best_idx]))

        # Kategori utama = yang confidence-nya tertinggi dari yang terdeteksi
        primary = max(predicted_labels, key=lambda c: confidence_dict[c])

        # Kode kategori (PLY, PRD, HRG, SUI)
        code_map = {
            'pelayanan': 'PLY',
            'produk':    'PRD',
            'harga':     'HRG',
            'suasana':   'SUI',
        }
        codes = [code_map.get(lbl, lbl.upper()[:3]) for lbl in predicted_labels]

        result = {
            'success': True,
            'kategori': predicted_labels,
            'kode': codes,
            'kode_string': ','.join(codes),
            'primary': primary,
            'primary_code': code_map.get(primary, primary.upper()[:3]),
            'primary_confidence': confidence_dict[primary],
            'confidence': confidence_dict,
            'segmen_count': len(segmen)
        }

        print(json.dumps(result, ensure_ascii=False))

    except Exception as e:
        import traceback
        print(json.dumps({
            'success': False,
            'error': str(e),
            'trace': traceback.format_exc()
        }, ensure_ascii=False))


if __name__ == '__main__':
    main()