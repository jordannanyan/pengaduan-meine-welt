"""
test_revisi.py - Skrip bukti hasil revisi untuk BAB IV.

Menjalankan predict.py pada sekumpulan contoh uji (termasuk contoh dari
penguji) lalu mencetak tabel ringkas: aspek + sentimen per aspek.

Menjawab dua poin revisi:
  1. Penanganan negasi: "tidak mahal", "tidak kotor" -> POSITIF.
  2. Multi-aspek: satu review banyak aspek -> semua aspek terdeteksi.

Cara pakai:
    python test_revisi.py
"""

import json
import subprocess
import sys
import os

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
PREDICT = os.path.join(BASE_DIR, 'predict.py')

# (teks, catatan/ekspektasi untuk dokumentasi)
SAMPLES = [
    # --- Poin 1: negasi ---
    ("harganya tidak mahal",                          "negasi -> harga POSITIF"),
    ("tempatnya tidak kotor",                         "negasi -> suasana POSITIF"),
    ("pelayanannya tidak ramah",                      "negasi -> pelayanan NEGATIF"),
    ("fasilitas tidak kotor dan harga tidak mahal",   "2 negasi -> dua-duanya POSITIF"),
    # --- Poin 2: multi-aspek ---
    ("makanan enak pelayanan cepat harga murah suasana nyaman",
                                                       "run-on tanpa tanda baca -> 4 aspek"),
    ("pelayanan ramah tapi makanan mahal dan tempat kotor",
                                                       "campuran -> sentimen beda per aspek"),
    ("pelayanannya lambat banget dan makanannya hambar",
                                                       "2 aspek negatif"),
    # --- Kontrol (tanpa negasi) ---
    ("harganya mahal sekali",                          "kontrol -> harga NEGATIF"),
    ("makanannya enak banget",                         "kontrol -> produk POSITIF"),
]


def run(text):
    out = subprocess.run([sys.executable, PREDICT, text],
                         capture_output=True, text=True, encoding='utf-8')
    line = [l for l in out.stdout.strip().splitlines() if l.strip()]
    if not line:
        return {'success': False, 'error': out.stderr[:300]}
    try:
        return json.loads(line[-1])
    except Exception:
        return {'success': False, 'error': 'output bukan JSON: ' + out.stdout[:200]}


def main():
    print("=" * 78)
    print("BUKTI HASIL REVISI - ABSA per-klausa + penanganan negasi")
    print("=" * 78)
    for text, note in SAMPLES:
        d = run(text)
        print(f"\nReview : {text}")
        print(f"Catatan: {note}")
        if not d.get('success'):
            print("  [GAGAL]", d.get('error'))
            continue
        print(f"  Sentimen keseluruhan : {d.get('sentimen')}")
        for a in d.get('aspek_sentimen', []):
            conf = a.get('sentimen_confidence')
            conf_s = f"{conf:.0%}" if isinstance(conf, (int, float)) else "-"
            print(f"    - {a['aspek']:<10} -> {a['sentimen']:<8} "
                  f"({conf_s}, metode={a.get('metode')})  klausa: \"{a.get('segmen')}\"")
    print("\n" + "=" * 78)


if __name__ == '__main__':
    main()
