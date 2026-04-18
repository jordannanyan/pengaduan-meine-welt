# Sistem Informasi Pengaduan Meine Welt Kafe

Demo skripsi - aplikasi web PHP + MySQL yang terintegrasi dengan model klasifikasi Naive Bayes Multi-Label.

## Stack

- **Backend:** PHP (vanilla) + MySQL
- **Frontend:** Bootstrap 5 (via CDN)
- **ML Model:** Python (dipanggil via `shell_exec` dari PHP)

## Setup Awal

### 1. Persiapan XAMPP
Pastikan Apache & MySQL sudah jalan di XAMPP.

### 2. Import Database
Buka phpMyAdmin → tab **Import** → upload file `database.sql` → klik **Go**.

### 3. Copy file model dari notebook
Copy 3 file berikut dari folder skripsi kamu ke folder `meine-welt-web/`:
- `model_nb_binary_relevance.pkl`
- `tfidf_vectorizer.pkl`
- `mlb_transformer.pkl`

### 4. Install Python dependencies
```
pip install joblib scikit-learn Sastrawi numpy pandas
```

### 5. Konfigurasi path Python
Edit `config/config.php` dan set `PYTHON_PATH` sesuai lokasi Python di komputer kamu.
Cek dengan: `where python` di CMD.

Contoh:
```php
define('PYTHON_PATH', 'C:\\Python310\\python.exe');
```

### 6. Jalankan Setup
Buka di browser: `http://localhost/meine-welt-web/setup.php`

Setup akan:
- Reset password admin & petugas
- Tes koneksi Python
- Tes prediksi model

### 7. Login
- **Admin:** `admin` / `admin123`
- **Petugas:** `petugas` / `petugas123`

### 8. Hapus `setup.php` setelah setup selesai.

## Struktur Folder

```
meine-welt-web/
├── predict.py               # Script prediksi Python
├── *.pkl                    # Model ML (copy dari notebook)
├── database.sql             # Schema MySQL
├── setup.php                # Setup awal
├── config/                  # Koneksi DB, auth, helpers
├── includes/                # Header & footer template
├── public/                  # Halaman pelanggan (submit & track)
├── auth/                    # Login / logout
├── admin/                   # Halaman Admin Kafe
├── petugas/                 # Halaman Petugas Kafe
├── uploads/                 # Folder lampiran
└── assets/                  # CSS kustom
```

## Akses

- **Pelanggan:** `http://localhost/meine-welt-web/public/index.php`
- **Login:** `http://localhost/meine-welt-web/auth/login.php`
