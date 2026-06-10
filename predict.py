"""
predict.py - Script prediksi aspek + sentimen pengaduan Meine Welt Kafe
Dipanggil dari PHP via shell_exec.

Pendekatan (revisi sidang):
  - ABSA per-klausa: review dipecah jadi klausa, tiap klausa diklasifikasi,
    lalu dikumpulkan pasangan (aspek, sentimen) unik. -> menjawab "banyak
    aspek hanya terbaca satu".
  - Negation handling berbasis lexicon + aturan pembalik polaritas, bekerja
    pada unit klausa. -> menjawab "tidak mahal" terbaca negatif.
  - Sentimen bersifat HYBRID: lexicon+aturan sebagai metode utama, model
    Naive Bayes (model_sentimen.pkl) sebagai fallback bila klausa tidak
    memuat kata berpolaritas. (Naive Bayes unigram tidak bisa menangani
    negasi sendirian; dataset juga tidak memiliki label sentimen sehingga
    retraining tidak dilakukan.)

Usage:
    python predict.py "teks pengaduan di sini"

Output (JSON):
    {
      "success": true,
      "kategori": ["harga","suasana"],
      "kode": ["HRG","SUI"],
      "kode_string": "HRG,SUI",
      "primary": "harga",
      "sentimen": "positif",                  # ringkasan keseluruhan
      "aspek_sentimen": [                      # hasil per-aspek (utama)
         {"aspek":"harga","kode":"HRG","sentimen":"positif", ...},
         ...
      ]
    }
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


# ================================================================
# KONFIGURASI LEKSIKAL (dipakai lintas fungsi)
# ================================================================

NEGATION_WORDS = {
    'tidak', 'bukan', 'kurang', 'belum', 'jangan',
    'tanpa', 'tak', 'tiada', 'enggan',
}

# Kata-kunci aspek -> kode kategori.
# Dipakai untuk (a) memecah kalimat run-on tanpa tanda baca, dan
# (b) jaring pengaman deteksi aspek bila model ML melewatkannya.
ASPECT_KEYWORDS = {
    # pelayanan (PLY)
    'pelayanan': 'PLY', 'pelayanannya': 'PLY', 'layanan': 'PLY', 'layan': 'PLY',
    'staff': 'PLY', 'staf': 'PLY', 'kasir': 'PLY', 'pelayan': 'PLY',
    'karyawan': 'PLY', 'pegawai': 'PLY', 'waiter': 'PLY', 'pramusaji': 'PLY',
    'melayani': 'PLY', 'ramah': 'PLY', 'jutek': 'PLY', 'judes': 'PLY',
    'sopan': 'PLY', 'cuek': 'PLY', 'sigap': 'PLY', 'responsif': 'PLY',
    # produk (PRD)
    'makanan': 'PRD', 'makan': 'PRD', 'minuman': 'PRD', 'minum': 'PRD',
    'rasa': 'PRD', 'rasanya': 'PRD', 'nasi': 'PRD', 'kopi': 'PRD', 'teh': 'PRD',
    'menu': 'PRD', 'porsi': 'PRD', 'masakan': 'PRD', 'hidangan': 'PRD',
    'roti': 'PRD', 'kue': 'PRD', 'ayam': 'PRD',
    'enak': 'PRD', 'lezat': 'PRD', 'hambar': 'PRD', 'basi': 'PRD', 'gurih': 'PRD',
    # harga (HRG)
    'harga': 'HRG', 'harganya': 'HRG', 'mahal': 'HRG', 'murah': 'HRG',
    'biaya': 'HRG', 'tarif': 'HRG', 'ongkos': 'HRG', 'sepadan': 'HRG',
    'terjangkau': 'HRG',
    # suasana / fasilitas / tempat (SUI)
    'suasana': 'SUI', 'tempat': 'SUI', 'tempatnya': 'SUI', 'ruangan': 'SUI',
    'ruang': 'SUI', 'fasilitas': 'SUI', 'kursi': 'SUI', 'meja': 'SUI',
    'toilet': 'SUI', 'wc': 'SUI', 'parkir': 'SUI', 'musik': 'SUI', 'ac': 'SUI',
    'bersih': 'SUI', 'kotor': 'SUI', 'nyaman': 'SUI', 'sempit': 'SUI',
    'berisik': 'SUI', 'jorok': 'SUI', 'pengap': 'SUI', 'luas': 'SUI',
}

# Lexicon polaritas sentimen (kata dasar / surface).
POSITIVE_WORDS = {
    'enak', 'lezat', 'nikmat', 'gurih', 'segar', 'sedap', 'mantap',
    'murah', 'terjangkau', 'sepadan',
    'ramah', 'sopan', 'sigap', 'cepat', 'gesit', 'responsif', 'profesional',
    'bersih', 'nyaman', 'sejuk', 'luas', 'rapi', 'wangi', 'hangat', 'lembut',
    'bagus', 'baik', 'memuaskan', 'puas', 'oke', 'rekomendasi', 'recommended',
    'keren', 'indah', 'cantik', 'instagramable',
}
NEGATIVE_WORDS = {
    'mahal', 'lambat', 'lama', 'lelet', 'lemot', 'antri', 'antre',
    'kotor', 'jorok', 'bau', 'pengap', 'sempit', 'berisik', 'bising', 'panas',
    'jutek', 'judes', 'kasar', 'cuek', 'sombong', 'galak',
    'basi', 'hambar', 'asin', 'pahit', 'gosong', 'mentah', 'keras', 'dingin',
    'mengecewakan', 'kecewa', 'buruk', 'jelek', 'parah', 'zonk',
}

# Subset kata-benda aspek -> dipakai sebagai BATAS pemecah kalimat run-on.
# Kata sifat (mahal, enak, kotor, ramah, ...) sengaja TIDAK jadi batas agar
# frasa "makanan mahal" / "makanan enak" tetap utuh sebagai satu klausa.
NOUN_ASPECT_KEYWORDS = {
    'pelayanan', 'pelayanannya', 'layanan', 'staff', 'staf', 'kasir', 'pelayan',
    'karyawan', 'pegawai', 'waiter', 'pramusaji',
    'makanan', 'minuman', 'rasa', 'rasanya', 'nasi', 'kopi', 'teh', 'menu',
    'porsi', 'masakan', 'hidangan', 'roti', 'kue', 'ayam',
    'harga', 'harganya', 'biaya', 'tarif', 'ongkos',
    'suasana', 'tempat', 'tempatnya', 'ruangan', 'ruang', 'fasilitas',
    'kursi', 'meja', 'toilet', 'wc', 'parkir', 'musik',
}

# Konjungsi pembalik / pemisah klausa
CONJ_SPLIT = ['tetapi', 'namun', 'tapi', 'sedangkan', 'padahal', 'walaupun',
              'meskipun', 'kalau', 'cuma', 'hanya']

CODE_TO_NAME = {'PLY': 'pelayanan', 'PRD': 'produk', 'HRG': 'harga', 'SUI': 'suasana'}
NAME_TO_CODE = {'pelayanan': 'PLY', 'produk': 'PRD', 'harga': 'HRG', 'suasana': 'SUI'}

SLANG_DICT = {
    'ga': 'tidak', 'gak': 'tidak', 'gk': 'tidak', 'ngga': 'tidak',
    'nggak': 'tidak', 'tdk': 'tidak', 'kagak': 'tidak', 'ndak': 'tidak',
    'kaga': 'tidak', 'gx': 'tidak', 'enggak': 'tidak', 'engga': 'tidak',
    'nda': 'tidak', 'nga': 'tidak',
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
    'recomended': 'rekomendasi', 'rekomen': 'rekomendasi',
    'worthit': 'sepadan', 'worth': 'sepadan',
    'pricey': 'mahal', 'overprice': 'mahal', 'overpriced': 'mahal',
    'slow': 'lambat', 'fast': 'cepat',
    'tasty': 'enak', 'yummy': 'enak', 'delicious': 'enak',
    'fresh': 'segar',
    'zonk': 'zonk', 'bad': 'buruk',
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


def normalize_tokens(text):
    """Bersihkan + normalisasi slang TANPA stemming/stopword removal.
    Dipakai untuk pencocokan lexicon (negasi & polaritas) supaya kata
    seperti 'tidak', 'mahal', 'ramah' tetap utuh."""
    text = str(text).lower().strip()
    text = re.sub(r'http\S+|www\S+', '', text)
    text = re.sub(r'[@#]\w+', '', text)
    text = re.sub(r'\d+', '', text)
    text = re.sub(r'[^a-z\s]', ' ', text)
    text = re.sub(r'\s+', ' ', text).strip()
    text = fix_elongation(text)
    tokens = [SLANG_DICT.get(t, t) for t in text.split()]
    return [t for t in tokens if t]


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

        base_dir = os.path.dirname(os.path.abspath(__file__))

        model = joblib.load(os.path.join(base_dir, 'model_nb_binary_relevance.pkl'))
        vectorizer = joblib.load(os.path.join(base_dir, 'tfidf_vectorizer.pkl'))
        mlb = joblib.load(os.path.join(base_dir, 'mlb_transformer.pkl'))

        sentiment_model = None
        sent_path = os.path.join(base_dir, 'model_sentimen.pkl')
        if os.path.exists(sent_path):
            try:
                sentiment_model = joblib.load(sent_path)
            except Exception:
                sentiment_model = None

        stemmer = StemmerFactory().create_stemmer()
        stopword_factory = StopWordRemoverFactory()
        # Pertahankan kata negasi & kata berpolaritas agar tidak terbuang.
        keep_words = NEGATION_WORDS | POSITIVE_WORDS | NEGATIVE_WORDS | set(ASPECT_KEYWORDS)
        final_stopwords = set(stopword_factory.get_stop_words()) - keep_words

        # ------------------------------------------------------------
        # Preprocessing untuk MODEL ML (selaras dengan data latih:
        # tanpa negation-merge, dengan stemming). Menghilangkan
        # train/serve mismatch yang sebelumnya membuang token negasi.
        # ------------------------------------------------------------
        def preprocess_for_model(text):
            tokens = normalize_tokens(text)
            tokens = [t for t in tokens if t not in final_stopwords or t in keep_words]
            tokens = [t for t in tokens if len(t) > 1]
            return stemmer.stem(' '.join(tokens))

        # ------------------------------------------------------------
        # Pemecah klausa: tanda baca -> konjungsi -> "dan" ->
        # batas kata-kunci aspek (untuk kalimat run-on tanpa pemisah).
        # ------------------------------------------------------------
        def split_by_aspect_keywords(tokens):
            """Pecah daftar token jadi beberapa klausa setiap kali muncul
            kata-kunci aspek baru yang berbeda dari klausa berjalan."""
            clauses = []
            current = []
            current_aspect = None
            for tok in tokens:
                asp = ASPECT_KEYWORDS.get(tok) if tok in NOUN_ASPECT_KEYWORDS else None
                if asp and current_aspect and asp != current_aspect and current:
                    clauses.append(current)
                    current = []
                current.append(tok)
                if asp:
                    current_aspect = asp
            if current:
                clauses.append(current)
            return clauses

        def sentence_split(text):
            text = str(text).lower().strip()
            segments = re.split(r'[.!?,;\n]+', text)

            expanded = []
            for seg in segments:
                parts = [seg]
                for conj in CONJ_SPLIT:
                    new_parts = []
                    for part in parts:
                        new_parts.extend(re.split(rf'\s+{conj}\s+', part))
                    parts = new_parts
                expanded.extend(parts)

            # Pecah pada "dan" untuk segmen panjang
            tmp = []
            for seg in expanded:
                seg = seg.strip()
                if len(seg.split()) > 4:
                    tmp.extend(re.split(r'\s+dan\s+', seg))
                else:
                    tmp.append(seg)

            # Pecah lagi pada batas kata-kunci aspek (kalimat run-on)
            result = []
            for seg in tmp:
                toks = normalize_tokens(seg)
                if not toks:
                    continue
                for clause in split_by_aspect_keywords(toks):
                    if clause:
                        result.append(' '.join(clause))

            return result if result else [text]

        # ------------------------------------------------------------
        # Sentimen lexicon + negasi pada satu klausa.
        # Return (label|None, confidence, metode)
        # ------------------------------------------------------------
        def lexicon_sentiment(tokens):
            pos = neg = 0
            for i, tok in enumerate(tokens):
                if tok in POSITIVE_WORDS:
                    pol = 1
                elif tok in NEGATIVE_WORDS:
                    pol = -1
                else:
                    continue
                # Cek negasi pada 1-2 token sebelumnya -> balik polaritas
                window = tokens[max(0, i - 2):i]
                if any(w in NEGATION_WORDS for w in window):
                    pol = -pol
                if pol > 0:
                    pos += 1
                else:
                    neg += 1

            total = pos + neg
            if total == 0:
                return None, None, 'none'
            if pos == neg:
                return None, None, 'tie'  # ambigu -> serahkan ke fallback ML
            label = 'positif' if pos > neg else 'negatif'
            conf = round(max(pos, neg) / total, 4)
            return label, conf, 'lexicon'

        def ml_sentiment(clause_text):
            if sentiment_model is None:
                return None, None
            try:
                X = vectorizer.transform([preprocess_for_model(clause_text)])
                proba = sentiment_model.predict_proba(X)[0]
                classes = list(sentiment_model.classes_)
                label_map = {0: 'negatif', 1: 'positif', '0': 'negatif', '1': 'positif',
                             'negative': 'negatif', 'positive': 'positif',
                             'neg': 'negatif', 'pos': 'positif'}
                idx = int(proba.argmax())
                return label_map.get(classes[idx], str(classes[idx])), round(float(proba[idx]), 4)
            except Exception:
                return None, None

        def clause_sentiment(clause_text, tokens):
            label, conf, method = lexicon_sentiment(tokens)
            if label is not None:
                return label, conf, method
            label, conf = ml_sentiment(clause_text)
            if label is not None:
                return label, conf, 'ml'
            return 'negatif', 0.5, 'default'

        # ------------------------------------------------------------
        # Aspek per klausa: model ML (>= threshold) UNION kata-kunci.
        # ------------------------------------------------------------
        threshold = 0.3

        def clause_aspects(clause_text, tokens):
            found = {}  # code -> confidence
            # 1) Model ML
            try:
                X = vectorizer.transform([preprocess_for_model(clause_text)])
                proba = model.predict_proba(X)[0]
                for i, cls in enumerate(mlb.classes_):
                    code = NAME_TO_CODE.get(str(cls), str(cls).upper()[:3])
                    conf = float(proba[i])
                    if conf >= threshold:
                        found[code] = max(found.get(code, 0.0), round(conf, 4))
            except Exception:
                pass
            # 2) Jaring pengaman kata-kunci
            for tok in tokens:
                code = ASPECT_KEYWORDS.get(tok)
                if code and code not in found:
                    found[code] = 0.5
            return found

        # ================================================================
        # ALUR UTAMA: prediksi per klausa -> pasangan (aspek, sentimen)
        # ================================================================
        segmen = sentence_split(teks)

        # aspect_code -> {'sentimen','sentimen_confidence','aspek_confidence','segmen','metode'}
        aspect_results = {}
        # confidence agregat per aspek (untuk kompatibilitas tampilan lama)
        agg_conf = {code: 0.0 for code in CODE_TO_NAME}

        for seg in segmen:
            tokens = normalize_tokens(seg)
            if not tokens:
                continue
            aspects = clause_aspects(seg, tokens)
            if not aspects:
                continue
            slabel, sconf, smethod = clause_sentiment(seg, tokens)

            for code, aconf in aspects.items():
                agg_conf[code] = max(agg_conf.get(code, 0.0), aconf)
                prev = aspect_results.get(code)
                cand = {
                    'sentimen': slabel,
                    'sentimen_confidence': sconf,
                    'aspek_confidence': aconf,
                    'segmen': seg.strip(),
                    'metode': smethod,
                }
                if prev is None:
                    aspect_results[code] = cand
                else:
                    # Konflik polaritas pada aspek sama: menangkan confidence
                    # sentimen tertinggi; bila seri, negatif diutamakan.
                    if sconf > prev['sentimen_confidence'] or \
                       (sconf == prev['sentimen_confidence'] and slabel == 'negatif'):
                        aspect_results[code] = cand

        # Fallback bila tidak ada aspek terdeteksi sama sekali:
        # ambil aspek dengan probabilitas tertinggi pada teks utuh.
        if not aspect_results:
            X = vectorizer.transform([preprocess_for_model(teks)])
            proba = model.predict_proba(X)[0]
            best = int(proba.argmax())
            code = NAME_TO_CODE.get(str(mlb.classes_[best]), str(mlb.classes_[best]).upper()[:3])
            toks = normalize_tokens(teks)
            slabel, sconf, smethod = clause_sentiment(teks, toks)
            aspect_results[code] = {
                'sentimen': slabel, 'sentimen_confidence': sconf,
                'aspek_confidence': round(float(proba[best]), 4),
                'segmen': teks.strip(), 'metode': smethod,
            }
            agg_conf[code] = round(float(proba[best]), 4)

        # ------------------------------------------------------------
        # Susun output
        # ------------------------------------------------------------
        aspek_sentimen = []
        for code, info in aspect_results.items():
            aspek_sentimen.append({
                'aspek': CODE_TO_NAME.get(code, code),
                'kode': code,
                'sentimen': info['sentimen'],
                'sentimen_kode': 'POS' if info['sentimen'] == 'positif' else 'NEG',
                'sentimen_confidence': info['sentimen_confidence'],
                'aspek_confidence': info['aspek_confidence'],
                'segmen': info['segmen'],
                'metode': info['metode'],
            })
        # Urutkan dari confidence aspek tertinggi
        aspek_sentimen.sort(key=lambda x: x['aspek_confidence'], reverse=True)

        predicted_codes = [a['kode'] for a in aspek_sentimen]
        predicted_labels = [a['aspek'] for a in aspek_sentimen]
        primary = aspek_sentimen[0]['aspek'] if aspek_sentimen else None
        primary_code = aspek_sentimen[0]['kode'] if aspek_sentimen else None

        # Ringkasan sentimen keseluruhan: negatif bila ada >=1 aspek negatif
        # (konteks pengaduan: aspek negatif yang perlu ditindaklanjuti).
        neg_items = [a for a in aspek_sentimen if a['sentimen'] == 'negatif']
        if neg_items:
            overall_sent = 'negatif'
            overall_conf = max(a['sentimen_confidence'] for a in neg_items)
        else:
            overall_sent = 'positif'
            overall_conf = max((a['sentimen_confidence'] for a in aspek_sentimen), default=0.5)

        confidence_dict = {CODE_TO_NAME[c]: round(agg_conf.get(c, 0.0), 4) for c in CODE_TO_NAME}

        result = {
            'success': True,
            'kategori': predicted_labels,
            'kode': predicted_codes,
            'kode_string': ','.join(predicted_codes),
            'primary': primary,
            'primary_code': primary_code,
            'primary_confidence': aspek_sentimen[0]['aspek_confidence'] if aspek_sentimen else 0.0,
            'confidence': confidence_dict,
            'segmen_count': len(segmen),
            'segmen': segmen,
            # ---- hasil per-aspek (fitur utama revisi) ----
            'aspek_sentimen': aspek_sentimen,
            # ---- ringkasan keseluruhan (kompatibilitas lama) ----
            'sentimen': overall_sent,
            'sentimen_kode': 'POS' if overall_sent == 'positif' else 'NEG',
            'sentimen_confidence': round(overall_conf, 4),
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
