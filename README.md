# Telegram Inventory Automation Bot

Backend-based Inventory Management Automation built using Laravel and integrated with Telegram Bot API via Webhook architecture.

---

## 📌 Project Overview

Telegram Inventory Automation Bot adalah sistem backend automation untuk manajemen stok barang yang dikontrol melalui Telegram Bot. Sistem ini memungkinkan admin melakukan pencatatan transaksi masuk/keluar, pengecekan stok secara real-time, serta pengelolaan data inventory langsung melalui chat interface Telegram.

Project ini dikembangkan sebagai implementasi RESTful backend service dengan pendekatan MVC dan webhook-based communication.

---

## 🏗 System Architecture

- **Architecture Pattern**: MVC (Model-View-Controller)
- **API Style**: RESTful Endpoint
- **Integration Method**: Telegram Webhook
- **Database**: MySQL (Relational Database)
- **ORM**: Eloquent ORM
- **Validation**: Server-side Validation
- **Transaction Logging**: Database-based activity logging

### 🔄 Request Flow

1. User mengirim command ke Telegram Bot  
2. Telegram mengirim request ke webhook endpoint (`/api/webhook`)  
3. Laravel memproses request melalui Controller  
4. Business Logic dieksekusi  
5. Database diperbarui (jika diperlukan)  
6. Response dikirim kembali ke Telegram API  

---

## 🛠 Tech Stack

- PHP 8+
- Laravel Framework
- MySQL
- Telegram Bot API
- Webhook Integration
- Eloquent ORM
- RESTful Architecture

---

## ⚙️ Installation Guide

### 1️⃣ Clone Repository

```bash
git clone https://github.com/auroralph/inventory-telegram-bot.git
cd inventory-telegram-bot
```

### 2️⃣ Install Dependencies

```bash
composer install
```

### 3️⃣ Setup Environment

```bash
cp .env.example .env
php artisan key:generate
```

Konfigurasi database pada file `.env`.

### 4️⃣ Run Migration

```bash
php artisan migrate
```

---

## ▶️ Running the Application

Jalankan server lokal:

```bash
php artisan serve
```

Set webhook Telegram ke endpoint berikut:

```
https://your-domain.com/api/webhook
```

---

## 📷 Feature Demonstration

- `/start` → Inisialisasi bot
- `/stok` → Cek stok barang
- `/cari {nama_produk}` → Pencarian produk
- `/editstok` → Update stok dengan tombol interaktif
- `/tambahbarang` → Tambah produk baru
- `/hapus` → Hapus produk
- `/log` → Lihat histori transaksi
- `/updatehari` → Update stok harian
- `/laporan` → Generate laporan

Screenshot tersedia pada folder `screenshots/`.

---

## 📁 Simplified Project Structure

```bash
app/
 ├── Http/
 │    ├── Controllers/
 │    ├── Middleware/
 ├── Models/
database/
routes/
 ├── api.php
 ├── web.php
```

---

## ✨ Key Features

- Real-time stock monitoring
- CRUD inventory management
- Interactive Telegram button handling
- Server-side validation
- Transaction logging system
- Webhook-based API integration
- Modular MVC architecture

---

## 🚀 Deployment Ready

Project telah dikonfigurasi untuk production-ready environment dan dapat di-deploy ke layanan hosting yang mendukung PHP & Laravel.

---

## 📄 License

This project is developed for educational and internship purposes.