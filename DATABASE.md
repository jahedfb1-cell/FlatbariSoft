# Green Valley BMS — ডাটাবেস কোথায় আছে

## আসল ডাটাবেস (এই কম্পিউটারে)

| | |
|---|---|
| ডাটাবেসের নাম | `green_valley_bms` |
| সার্ভার | **`C:\xampp2`** → MariaDB 10.4.32 |
| ডেটা ফোল্ডার | `C:\xampp2\mysql\data\green_valley_bms` |
| Host / Port | `127.0.0.1` / `3306` |
| ইউজার / পাসওয়ার্ড | `root` / (খালি) |
| কনফিগ | `.env` → `DB_*` |

> ⚠️ **এই কম্পিউটারে দুটো XAMPP আছে।** `C:\xampp` আর `C:\xampp2` দুটোই পোর্ট ৩৩০৬ চায়।
> `C:\xampp`-এ একটা **পুরনো খালি** `green_valley_bms` পড়ে আছে। ভুল XAMPP চালু করলে
> অ্যাপ সেটাই দেখবে — ফ্ল্যাট ০, বিল ০ — যদিও আসল ডেটা `C:\xampp2`-এ নিরাপদ আছে।
> (২০২৬-১০-১১ তারিখে ঠিক এটাই ঘটেছিল।)

## ডাটাবেস খুঁজে পাওয়ার ৩টা সহজ উপায়

**১. ডাবল-ক্লিক:** প্রজেক্ট ফোল্ডারের **`find-database.bat`**
মেশিনের সব MySQL ইনস্টল খুঁজে দেখায় কোনটা চালু আছে, কোনটায় আসল (সবচেয়ে নতুন ব্যবসায়িক) ডেটা,
আর ভুল সার্ভার চালু থাকলে কী করতে হবে। ফলাফল সেভ হয় **`DATABASE-STATUS.txt`** ফাইলে।
MySQL বন্ধ থাকলেও কাজ করে (ডিস্কের ফাইল দেখে)। কিছু চালু না থাকলে:
`find-database.bat start` — সঠিকটা নিজে চালু করে দেয় (চালু থাকা সার্ভার কখনো বন্ধ করে না)।

**২. অ্যাপের ভেতর থেকে:**
```bash
php artisan db:where            # অ্যাপ এখন কোন MySQL-এর সাথে যুক্ত, কত রো আছে
php artisan db:where --write    # একই রিপোর্ট DATABASE-STATUS.txt-তে সেভ
```
শেষে লেখা থাকে `DATA PRESENT` (আসল ডাটাবেস) অথবা `EMPTY` (সম্ভবত ভুল MySQL-এ আছেন)।

**৩. হাতে খুঁজতে চাইলে:** `C:\xampp2\mysql\data\green_valley_bms` ফোল্ডারে `flats.ibd`, `bills.ibd`… ফাইলগুলো দেখুন।

## সঠিক MySQL চালু করা

XAMPP Control Panel থেকে **`C:\xampp2`**-এরটা চালান। অথবা কমান্ডে:
```bat
"C:\xampp2\mysql\bin\mysqld.exe" --defaults-file="C:\xampp2\mysql\bin\my.ini" --standalone
```

## ব্যাকআপ (একটা কমান্ড)
```bat
"C:\xampp2\mysql\bin\mysqldump.exe" -uroot green_valley_bms > green_valley_bms_backup.sql
```

## ফাইলগুলো
| ফাইল | কাজ |
|---|---|
| `find-database.bat` | ডাবল-ক্লিক করে ডাটাবেস খোঁজা |
| `scripts/find-database.ps1` | আসল খোঁজার স্ক্রিপ্ট |
| `app/Console/Commands/DatabaseWhere.php` | `php artisan db:where` |
| `DATABASE-STATUS.txt` | সর্বশেষ রিপোর্ট (প্রতি মেশিনে আলাদা, git-এ যায় না) |
