# AidatCep - Apartman Yönetim Sistemi

AidatCep, apartman ve site yönetimlerinin finansal işlemlerini (aidat takibi, gider yönetimi, tahsilatlar) kolayca yönetebileceği web tabanlı bir uygulamadır.

## Özellikler

- **Aidat Yönetimi**: Daire bazlı aidat tahakkuku ve takibi
- **Otomatik Aidat Planlama**: Aylık/yıllık tekrarlayan aidat oluşturma
- **Tahsilat Yönetimi**: Gelen ödemelerin kaydı ve aidatlara tahsisi
- **Gider Takibi**: Apartman giderlerinin kategorilere göre kaydı
- **Kasa Yönetimi**: Nakit hareketleri ve kasa durumu
- **Çoklu Apartman**: Tek hesapla birden fazla apartman yönetimi
- **Kullanıcı Portalı**: Sakinlerin borç durumunu görüntülemesi
- **Raporlar**: Hesap ekstresi ve finansal raporlar

## Gereksinimler

- PHP 8.2+
- MySQL 8.0+ veya MariaDB 10.6+
- Composer 2.x
- Node.js 18+ ve NPM
- Git

## Kurulum (Local Development)

### 1. Projeyi Klonla

```bash
git clone https://github.com/kullaniciadi/KapitalOnlineApartmanYonetim.git aidatcep
cd aidatcep
```

### 2. Composer Paketlerini Yükle

```bash
composer install
```

### 3. Environment Ayarları

```bash
cp .env.example .env
php artisan key:generate
```

`.env` dosyasını düzenle:
```
APP_NAME="AidatCep"
APP_URL=http://localhost

# MySQL Ayarları (kendi bilgilerinizi girin)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aidatcep_db
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 4. Veritabanı Oluştur

MySQL'de veritabanı oluştur:
```sql
CREATE DATABASE aidatcep_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Migration ve Seed Çalıştır

```bash
php artisan migrate
```

### 6. Asset Build

```bash
npm install
npm run build
```

### 7. Geliştirme Sunucusunu Başlat

```bash
php artisan serve
```

Tarayıcıda `http://localhost:8000` adresine git.

## Production Deploy (Ubuntu Sunucu)

### 1. Sunucu Gereksinimleri

```bash
# PHP ve eklentiler
sudo apt update
sudo apt install php8.2 php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip unzip

# MySQL
sudo apt install mysql-server

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 2. Proje Dizini ve İzinler

```bash
sudo mkdir -p /var/www/aidatcep
sudo chown -R $USER:$USER /var/www/aidatcep
cd /var/www/aidatcep
git clone https://github.com/kullaniciadi/KapitalOnlineApartmanYonetim.git .
```

### 3. Production Kurulum

```bash
# Composer (production optimize)
composer install --no-dev --optimize-autoloader

# Environment
cp .env.example .env
nano .env  # APP_URL, DB bilgileri, MAIL ayarlarını gir
php artisan key:generate

# Optimize
cp .env .env.backup  # Yedek al
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migration
php artisan migrate --force

# İzinler
chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache

# Build
npm ci
npm run build
```

### 4. Nginx Yapılandırması

`/etc/nginx/sites-available/aidatcep`:

```nginx
server {
    listen 80;
    server_name aidatcep.com www.aidatcep.com;
    root /var/www/aidatcep/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktif et:
```bash
sudo ln -s /etc/nginx/sites-available/aidatcep /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 5. SSL (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d aidatcep.com -d www.aidatcep.com
```

## Güncelleme (Sunucu)

```bash
cd /var/www/aidatcep
git pull origin main

composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

chmod -R 775 storage bootstrap/cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

## Yararlı Komutlar

```bash
# Cache temizle
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Logları gör
php artisan tail  # veya tail -f storage/logs/laravel.log

# Maintenance mode
php artisan down  # Bakım modu aç
php artisan up    # Bakım modu kapat
```

## Lisans

Bu proje özel mülkiyet altındadır. Tüm hakları saklıdır.
