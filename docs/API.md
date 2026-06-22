# SIMPEG Internal API

Dokumentasi awal endpoint API di branch `dev-app`.

## Base URL

Environment yang saat ini dipakai:

- Local: `http://localhost:8081/dummy/api`
- Server: `https://simpeg.bkkjateng.co.id/api`

## Format Response

Semua endpoint saat ini mengembalikan JSON dengan format umum:

```json
{
  "success": true,
  "data": {},
  "meta": {}
}
```

Jika gagal:

```json
{
  "success": false,
  "message": "Pesan error"
}
```

## Endpoint

### 1. Health Check

`GET /health`

Contoh:

```text
http://localhost:8081/dummy/api/health
```

Contoh response:

```json
{
  "success": true,
  "data": {
    "name": "SIMPEG Internal API",
    "status": "ok",
    "timestamp": "2026-06-18T00:00:00+07:00",
    "version": "dev-app-fe-bootstrap"
  }
}
```

### 2. Root Info API

`GET /`

Contoh:

```text
http://localhost:8081/dummy/api
```

Endpoint ini menampilkan informasi API dan daftar route awal yang tersedia.

### 3. List Pegawai

`GET /pegawai`

Query parameter yang tersedia:

- `page` default `1`
- `limit` default `20`, maksimal `100`
- `q` untuk pencarian `id_peg`, `nip`, atau `nama`
- `unit_kerja` untuk filter unit kerja

Contoh:

```text
http://localhost:8081/dummy/api/pegawai
http://localhost:8081/dummy/api/pegawai?limit=10
http://localhost:8081/dummy/api/pegawai?q=andi
http://localhost:8081/dummy/api/pegawai?unit_kerja=KC01
```

Contoh response ringkas:

```json
{
  "success": true,
  "data": [
    {
      "id_peg": "117-061",
      "nip": "1234567890",
      "nama": "Nama Pegawai",
      "jk": "L",
      "email": "pegawai@example.com",
      "telp": "08123456789",
      "status_kepeg": "Tetap",
      "status_aktif": "Y",
      "foto": "foto.jpg",
      "jabatan": "Kabid Operasional",
      "unit_kerja": "KC01",
      "tmt_jabatan": "2025-01-01"
    }
  ],
  "meta": {
    "page": 1,
    "limit": 20,
    "total": 1
  }
}
```

### 4. Detail Pegawai

`GET /pegawai/{id_peg}`

Contoh:

```text
http://localhost:8081/dummy/api/pegawai/117-061
```

### 5. Data Keluarga Pegawai

`GET /pegawai/{id_peg}/keluarga`

Contoh:

```text
http://localhost:8081/dummy/api/pegawai/117-061/keluarga
```

Response berisi:

- data pegawai
- data pasangan
- data anak
- data orang tua

## Cara Test Cepat

### Browser

Buka langsung:

```text
http://localhost:8081/dummy/api/health
```

### cURL

```bash
curl "http://localhost:8081/dummy/api/health"
curl "http://localhost:8081/dummy/api/pegawai?limit=5"
curl "http://localhost:8081/dummy/api/pegawai/117-061"
curl "http://localhost:8081/dummy/api/pegawai/117-061/keluarga"
```

### Postman

1. Buat collection baru `SIMPEG Internal API`
2. Tambahkan request `GET`
3. Isi URL sesuai endpoint di atas
4. Tekan `Send`

Saat ini endpoint belum memakai autentikasi khusus, jadi testing masih bisa langsung dilakukan di environment internal/lokal.

## Catatan Tahap Berikutnya

Tahap selanjutnya yang direncanakan:

- tambah autentikasi API
- tambah endpoint create/update/delete
- tambah endpoint referensi master
- pindahkan FE lama supaya baca data dari REST API
