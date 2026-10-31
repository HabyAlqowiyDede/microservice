# Microservices: Order Service

**Nama:** Haby Alqowiy Dede | **NIM:** 2401163009 | **Mata kuliah:** Microservices, Politeknik Negeri Padang

## Daftar isi

1. [Deskripsi](#1-deskripsi)
2. [Arsitektur](#2-arsitektur)
3. [Cara menjalankan](#3-cara-menjalankan)
4. [Daftar endpoint](#4-daftar-endpoint-order-service)
5. [Environment variable](#5-environment-variable-order-service)
6. [Keputusan desain dan keterbatasan](#6-keputusan-desain-dan-keterbatasan)
7. [Video demo](#7-video-demo)
8. [Informasi pengumpulan](#8-informasi-pengumpulan)

## 1. Deskripsi

Proyek ini adalah toko online berbasis microservices. Tiga service dari kelas (product-service, cart-service, review-service) sudah ada, dan UTS ini menambahkan **order-service**: layanan yang mengubah isi keranjang pengguna menjadi pesanan.

Order-service dibangun dengan **Laravel** dan **MySQL**, memakai database sendiri, dan hanya berkomunikasi dengan service lain lewat REST API. Saat checkout, order-service membaca keranjang dari cart-service, mengambil data produk dari product-service, memeriksa aturan bisnis, menyimpan pesanan beserta item-itemnya, lalu meminta cart-service mengosongkan keranjang.

| Service           | Port     | Database                        |
| ----------------- | -------- | ------------------------------- |
| product-service   | 3000     | MySQL (product-db, host 3306)   |
| cart-service      | 3001     | PostgreSQL (cart-db, host 5432) |
| review-service    | 3002     | MongoDB (review-db, host 27017) |
| **order-service** | **3003** | **MySQL (order-db, host 3307)** |

## 2. Arsitektur

### 2.1 Alur request antar service

```text
                          +--------------------+      +------------+
                     +--->|   cart-service     |----->|  cart-db   |
                     |    |   (3001)           |      | PostgreSQL |
 Client              |    +--------------------+      +------------+
 (Postman/curl)      |  GET /carts/{userId}, DELETE /carts/{userId}
     |               |
     |  POST /orders |    +--------------------+      +------------+
     +-------------->+--->|  product-service   |----->| product-db |
        order-service|    |   (3000)           |      |   MySQL    |
          (3003)     |    +--------------------+      +------------+
             |       |  GET /products/{id}
             v
        +-----------+
        | order-db  |  MySQL, milik order-service sendiri
        +-----------+
```

Urutan pada `POST /orders`:

1. Client mengirim `user_id`.
2. Order-service meminta isi keranjang ke cart-service.
3. Untuk setiap item, order-service meminta nama, harga, dan stok ke product-service.
4. Order-service memeriksa aturan bisnis: keranjang tidak kosong, produk masih ada, stok cukup.
5. Pesanan dan item-itemnya disimpan dalam satu transaksi database (nama dan harga disalin sebagai snapshot).
6. Order-service meminta cart-service mengosongkan keranjang.
7. Order-service membalas client dengan pesanan yang terbentuk (201).

### 2.2 Pembagian lapisan di order-service

```text
routes -> Form Request -> Controller -> Service -> Client / Model Eloquent
```

| Lapisan      | Lokasi                          | Tanggung jawab                                                                                                                       |
| ------------ | ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| Routes       | `routes/api.php`                | Memetakan URL ke method controller, membatasi `{id}` hanya angka                                                                     |
| Form Request | `app/Http/Requests`             | Validasi input; gagal menghasilkan 422 berstruktur `message` dan `errors`                                                            |
| Controller   | `app/Http/Controllers`          | Menerima request, memanggil service, menerjemahkan hasil atau exception menjadi response HTTP. Tidak berisi aturan bisnis atau query |
| Service      | `app/Services/OrderService.php` | Seluruh logika bisnis: checkout, pembayaran, pencarian. Tidak mengenal HTTP                                                          |
| Client       | `app/Clients`                   | Satu-satunya tempat panggilan HTTP ke cart-service dan product-service, dengan timeout dan penerjemahan kegagalan jaringan           |
| Model        | `app/Models`                    | `Order` dan `OrderItem` (relasi `hasMany` dan `belongsTo`)                                                                           |
| Exception    | `app/Exceptions`                | Kasus bisnis dan kegagalan service lain sebagai exception khusus                                                                     |
| Resource     | `app/Http/Resources`            | Membentuk JSON pesanan (angka, bukan teks; tanggal ISO 8601)                                                                         |

Pembagian lapisan ini mengikuti pola yang dipakai di cart-service pada kelas.

### 2.3 Struktur repositori

```text
microservice/
├── product-service/
├── cart-service/
├── review-service/
├── order-service/
├── docker-compose.yml
└── README.md
```

## 3. Cara menjalankan

### 3.1 Prasyarat

- **Docker Desktop** (sudah termasuk Docker Compose v2) dalam keadaan **menyala**. Periksa dengan `docker --version` dan `docker compose version`.
- **Git**.
- Port berikut harus kosong di komputer: **3000, 3001, 3002, 3003, 3306, 3307, 5432, 27017**. Hentikan MySQL, PostgreSQL, atau MongoDB lokal yang sedang berjalan jika ada.
- Tidak perlu PHP atau Composer. Konfigurasi utama sudah ada di `docker-compose.yml`; file `.env` hanya perlu disalin dari contohnya (langkah 3).

### 3.2 Langkah menjalankan

1. Clone repositori ke folder yang masih kosong, lalu masuk ke dalamnya:

```bash
git clone https://github.com/HabyAlqowiyDede/microservice.git
cd microservice
```

2. Pastikan seluruh folder service ikut ter-clone:

```bash
ls
# product-service  cart-service  review-service  order-service  docker-compose.yml  README.md
```

3. Salin file environment dari contohnya. File `.env` tidak ikut ter-commit karena berisi konfigurasi lokal, jadi harus dibuat setelah clone:

```bash
cp order-service/.env.example order-service/.env
cp cart-service/.env.example cart-service/.env
```

Di PowerShell Windows:

```powershell
Copy-Item order-service/.env.example order-service/.env
Copy-Item cart-service/.env.example cart-service/.env
```

Nilai yang penting (alamat database, URL antar service, timeout) sudah ditimpa lewat `docker-compose.yml`, sehingga hasil salinan tidak perlu diedit. Jangan memasukkan `.env` ke commit.

4. Bangun dan jalankan semua container dari **folder root** (tempat `docker-compose.yml` berada):

```bash
docker compose up --build -d
```

Build pertama membutuhkan beberapa menit karena mengunduh image dan menginstal dependensi.

5. Tunggu sampai semua service berstatus `running` dan order-service `healthy` (sekitar 1 sampai 2 menit setelah build selesai):

```bash
docker compose ps
```

6. Uji order-service:

```bash
curl http://localhost:3003/health
```

Hasil yang diharapkan:

```json
{ "status": "ok", "service": "order-service" }
```

Migrasi database order-service dijalankan otomatis saat container menyala, jadi tidak ada perintah migrasi manual.

### 3.3 Perintah berguna

```bash
# Melihat log order-service
docker compose logs -f order-service

# Mematikan dan menyalakan product-service (untuk menguji 503)
docker compose stop product-service
docker compose start product-service

# Menjalankan order-service dalam mode debug
APP_DEBUG=true docker compose up -d order-service

# Menghentikan semuanya
docker compose down

# Mulai dari nol (menghapus seluruh data database)
docker compose down -v
```

> Di PowerShell, set variabel dulu: `$env:APP_DEBUG="true"; docker compose up -d order-service`.

### 3.4 Jika gagal setelah clone

| Gejala                                                        | Penyebab                                             | Solusi                                                                                                                                       |
| ------------------------------------------------------------- | ---------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `Cannot connect to the Docker daemon`                         | Docker Desktop belum menyala                         | Buka Docker Desktop, tunggu sampai siap, ulangi                                                                                              |
| `no configuration file provided: not found`                   | Perintah dijalankan bukan dari folder root           | `cd` ke folder yang berisi `docker-compose.yml`                                                                                              |
| `port is already allocated`                                   | Port dipakai program lain                            | Hentikan program itu (misalnya MySQL lokal di 3306) lalu ulangi                                                                              |
| `order-service` restart terus                                 | Database belum siap, atau sisa volume lama           | Lihat `docker compose logs order-service`; coba `docker compose down -v` lalu `up --build -d` lagi                                           |
| `exec ... no such file or directory` saat order-service start | Akhir baris `entrypoint.sh` berformat Windows (CRLF) | Jalankan `git config core.autocrlf false`, clone ulang, lalu build ulang                                                                     |
| `COPY failed` atau file tidak ditemukan saat build            | File belum ter-commit atau ter-push                  | Periksa dengan `git ls-files order-service/Dockerfile order-service/docker/entrypoint.sh order-service/composer.lock`; semuanya harus muncul |
| Respons 503 padahal semua service hidup                       | Service lain belum selesai start                     | Tunggu 1 menit lalu coba lagi, atau lihat `docker compose ps`                                                                                |
| Perubahan kode tidak terlihat                                 | Image lama masih dipakai                             | `docker compose up -d --build order-service`                                                                                                 |

## 4. Daftar endpoint order-service

Base URL: `http://localhost:3003`

| Method | Endpoint                | Fungsi                                                                | Sukses | Gagal                                                                                                                                      |
| ------ | ----------------------- | --------------------------------------------------------------------- | ------ | ------------------------------------------------------------------------------------------------------------------------------------------ |
| GET    | `/health`               | Mengecek service hidup                                                | 200    | -                                                                                                                                          |
| POST   | `/orders`               | Checkout: membuat pesanan dari isi keranjang                          | 201    | 422 body tidak valid; 409 keranjang kosong, produk tidak ada, atau stok kurang; 503 cart/product-service bermasalah; 500 error tak terduga |
| GET    | `/orders/{id}`          | Detail satu pesanan beserta item                                      | 200    | 404 pesanan tidak ditemukan (id non-angka juga 404)                                                                                        |
| GET    | `/orders/user/{userId}` | Daftar pesanan satu user, terbaru di atas, filter opsional `?status=` | 200    | 422 nilai status tidak valid                                                                                                               |
| PATCH  | `/orders/{id}/pay`      | Bayar (simulasi): PENDING menjadi PAID                                | 200    | 404 tidak ditemukan; 409 status bukan PENDING                                                                                              |

Semua endpoint yang menyentuh database juga dapat mengembalikan 500. Pada 500 dan 503, detail teknis (bagian `debug`) hanya muncul saat `APP_DEBUG=true`.

### 4.1 Contoh request

**Health**

```bash
curl -i http://localhost:3003/health
```

**Checkout** (keranjang user 7 harus sudah berisi item, lihat bagian 4.2)

```bash
curl -i -X POST http://localhost:3003/orders \
  -H "Content-Type: application/json" \
  -d '{"user_id": 7}'
```

Response sukses (201):

```json
{
  "message": "Pesanan berhasil dibuat",
  "data": {
    "id": 1,
    "user_id": 7,
    "status": "PENDING",
    "total_amount": 1050000,
    "items": [
      {
        "product_id": 1,
        "product_name": "Keyboard Mekanikal",
        "unit_price": 450000,
        "quantity": 2,
        "subtotal": 900000
      },
      {
        "product_id": 2,
        "product_name": "Mouse Wireless",
        "unit_price": 150000,
        "quantity": 1,
        "subtotal": 150000
      }
    ],
    "created_at": "2026-10-20T03:15:00+00:00",
    "updated_at": "2026-10-20T03:15:00+00:00"
  }
}
```

Body tanpa `user_id` (422):

```json
{
  "message": "Data yang dikirim tidak valid",
  "errors": { "user_id": ["Field user_id wajib diisi"] }
}
```

Stok tidak cukup (409):

```json
{ "message": "Stok Monitor 24 Inch tidak mencukupi: diminta 11, tersedia 10" }
```

Product-service mati (503):

```json
{ "message": "Product service tidak dapat dihubungi, coba lagi nanti" }
```

**Detail pesanan**

```bash
curl -i http://localhost:3003/orders/1
```

**Daftar pesanan user (dengan dan tanpa filter)**

```bash
curl -i "http://localhost:3003/orders/user/7"
curl -i "http://localhost:3003/orders/user/7?status=PENDING"
```

**Bayar pesanan**

```bash
curl -i -X PATCH http://localhost:3003/orders/1/pay
```

### 4.2 Menyiapkan keranjang untuk pengujian

Data produk awal berasal dari `init.sql` product-service (Keyboard Mekanikal id 1 stok 25, Mouse Wireless id 2 stok 40, Monitor 24 Inch id 3 stok 10).

```bash
curl -i -X POST http://localhost:3001/carts \
  -H "Content-Type: application/json" \
  -d '{"user_id": 7, "product_id": 1, "quantity": 2}'
```

## 5. Environment variable (order-service)

Diberikan lewat `docker-compose.yml`. File `order-service/.env.example` berisi contoh nilainya dan disalin menjadi `.env` pada langkah 3 cara menjalankan.

| Variabel              | Contoh nilai                  | Keterangan                                                                   |
| --------------------- | ----------------------------- | ---------------------------------------------------------------------------- |
| `APP_NAME`            | `order-service`               | Nama aplikasi                                                                |
| `APP_ENV`             | `local`                       | Lingkungan aplikasi                                                          |
| `APP_KEY`             | (kosong)                      | Jika kosong, entrypoint membangkitkan kunci sementara saat container menyala |
| `APP_DEBUG`           | `false`                       | `true` menampilkan detail teknis pada response 500 dan 503                   |
| `LOG_CHANNEL`         | `stderr`                      | Log keluar ke stderr container, dibaca lewat `docker compose logs`           |
| `LOG_LEVEL`           | `debug`                       | Level log minimum                                                            |
| `DB_CONNECTION`       | `mysql`                       | Driver database                                                              |
| `DB_HOST`             | `order-db`                    | Nama service MySQL di jaringan Docker                                        |
| `DB_PORT`             | `3306`                        | Port MySQL di dalam jaringan Docker                                          |
| `DB_DATABASE`         | `orderdb`                     | Nama database                                                                |
| `DB_USERNAME`         | `appuser`                     | User database                                                                |
| `DB_PASSWORD`         | `apppassword`                 | Password database (nilai untuk pengembangan, bukan rahasia sungguhan)        |
| `SESSION_DRIVER`      | `array`                       | Tidak memakai sesi                                                           |
| `CACHE_STORE`         | `array`                       | Tidak memakai cache                                                          |
| `QUEUE_CONNECTION`    | `sync`                        | Tidak memakai antrean                                                        |
| `CART_SERVICE_URL`    | `http://cart-service:3001`    | Alamat cart-service                                                          |
| `PRODUCT_SERVICE_URL` | `http://product-service:3000` | Alamat product-service                                                       |
| `HTTP_TIMEOUT`        | `5`                           | Timeout panggilan ke service lain dalam detik (dibatasi maksimal 5)          |

Database order-service dibuka ke komputer pada port **3307** (bukan 3306, karena sudah dipakai product-db).

## 6. Keputusan desain dan keterbatasan

### Keputusan desain

- **Snapshot nama dan harga ke `order_items`.** Harga produk bisa berubah kapan saja, sedangkan pesanan lama tidak boleh ikut berubah. Karena itu `product_name` dan `unit_price` disalin saat checkout, dan `order_items` tidak memiliki foreign key ke produk (tabel produk ada di database service lain).
- **Satu transaksi database untuk pesanan dan item.** Jika penyimpanan item gagal, pesanan ikut dibatalkan, sehingga tidak ada pesanan setengah jadi.
- **Panggilan ke service lain dilakukan sebelum transaksi dimulai.** Transaksi database tidak ditahan sambil menunggu respons jaringan.
- **Pengosongan keranjang dilakukan setelah pesanan tersimpan dan di luar transaksi.** Pesanan yang sudah sah tidak dibatalkan hanya karena keranjang gagal dikosongkan; kegagalannya dicatat sebagai warning di log.
- **Pemisahan kegagalan bisnis dan kegagalan infrastruktur.** Pelanggaran aturan bisnis (keranjang kosong, produk hilang, stok kurang, pesanan sudah dibayar) menghasilkan 409. Service lain yang tidak bisa dihubungi menghasilkan 503. Keduanya memakai exception khusus yang berbeda.
- **Semua panggilan HTTP lewat lapisan client dengan timeout maksimal 5 detik**, agar request tidak menggantung bila service lain macet.
- **Detail teknis hanya dikirim saat `APP_DEBUG=true`**, agar informasi internal tidak bocor ke client.

### Keterbatasan yang disadari

- **Stok hanya diperiksa, tidak dikurangi atau dikunci** (sesuai ruang lingkup soal). Dua checkout bersamaan bisa sama-sama lolos untuk stok yang sama. Perbaikan: reservasi stok di product-service.
- **Keranjang bisa masih berisi setelah pesanan jadi** bila pengosongan gagal, sehingga user berpotensi checkout dua kali. Perbaikan: idempotency key atau proses ulang di latar belakang.
- **Komunikasi sinkron lewat REST.** Jika product-service atau cart-service mati, checkout ikut gagal (503). Sengaja dipilih karena sederhana dan sesuai ruang lingkup (tanpa message broker).
- **Perhitungan harga memakai tipe float yang dibulatkan 2 desimal.** Untuk produksi sebaiknya memakai bilangan bulat satuan terkecil atau pustaka desimal.
- **Tanpa autentikasi.** `user_id` dipercaya apa adanya.

## 7. Video demo

Tautan: https://drive.google.com/file/d/1sNyXjkFfjbKGVhe1ge1vdl80CzoD9ZlL/view?usp=sharing

## 8. Informasi pengumpulan

- Repositori: <https://github.com/HabyAlqowiyDede/microservice>
- SHA commit terakhir: dicantumkan pada Form UTS saat mengumpulkan.
