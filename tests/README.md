# QR Menu Suite — Test Ortamları

İki ayrı test katmanı vardır. Birbirinden bağımsızdır, ikisi de canlı siteden
ve gerçek müşteri verisinden tamamen izoledir.

## 1) Hızlı mantık testleri (WordPress gerektirmez)

`tests/test-suite.php` — WordPress fonksiyonlarını `tests/stubs-wordpress.php`
içinde taklit eden hafif bir koşucu (PHPUnit değildir). Modül mantığını,
sanitization/nonce/capability kontrollerini, kısa kod çıktısını saniyeler
içinde doğrular.

```bash
php tests/test-suite.php
```

Başarılı çıktı: `Tüm testler geçti (N doğrulama)`, çıkış kodu `0`.
Başarısız testler `✗` ile listelenir, çıkış kodu `1`.

## 2) Docker WordPress + MySQL ortamı (`tests/docker/`)

Gerçek bir WordPress kurulumunda aktivasyon, veritabanı tablo oluşturma ve
yönetim paneli davranışını test etmek için. Servisler:

- **db** — MariaDB 10.11, kalıcı Docker volume (`qrms_db_data`)
- **wordpress** — PHP 8.1 + Apache, eklenti kaynak kodu repo kökünden
  `wp-content/plugins/qr-menu-suite` olarak bağlanır (bind mount — kopya
  değil, repo'daki her değişiklik anında yansır)
- **wpcli** — WordPress kurulumunu ve eklenti aktivasyonunu otomatik yapan
  tek seferlik yardımcı servis

Image'lar Docker Hub yerine `mirror.gcr.io` üzerinden çekilir (bazı bulut
ortamlarında Docker Hub CDN'i erişilebilir olmayabiliyor; `mirror.gcr.io`
herkese açık bir aynadır, ekstra kimlik doğrulama gerekmez).

### Başlatma

```bash
cd tests/docker
cp .env.example .env      # ilk kurulumda; değerler zaten güvenli test varsayılanları
docker compose up -d db wordpress
docker compose run --rm wpcli
```

`wpcli` servisi: veritabanını bekler, WordPress'i kurar (`admin` /
`.env`'deki `WP_ADMIN_PASSWORD`), eklentiyi etkinleştirir ve **lisans
sunucusuna hiç bağlanmadan** `qrms_active_modules` option'ını 13 modülün
tamamıyla doldurur (gerçek lisans akışı `QRMS_License_Client` üzerinden
`qrmenuofficial.com`'a gider; test ortamında bu sunucuya erişim yok, bu
yüzden option doğrudan yazılır — kod değişikliği değil, kurulum adımı).

Site: `http://localhost:8080` (host'ta port zaten dolu ise `.env` içindeki
`WORDPRESS_PORT`'u değiştirin). Yönetim paneli: `/wp-admin`.

### Yeniden başlatma (yeni oturumlarda)

Veritabanı `qrms_db_data` volume'unda kalıcıdır; container'lar durup tekrar
`docker compose up -d db wordpress` ile kalktığında kurulum sıfırdan
başlamaz. `wpcli` servisi tekrar çalıştırılırsa zaten kurulu olan WordPress'i
ve aktif eklentiyi atlayıp sadece emin olmak için tekrar kontrol eder.

### Sıfırdan başlamak için

```bash
cd tests/docker
docker compose down -v   # DİKKAT: qrms_db_data ve qrms_wp_data volume'larını siler
```

### Durdurma (veriyi korur)

```bash
docker compose stop
```

### Sorun giderme

- **PHP hatalarını görme:** `docker compose logs wordpress` veya container
  içinde `wp-content/debug.log` (`WORDPRESS_DEBUG=1` ile `WP_DEBUG_LOG`
  açıktır).
- **wp-cli komutu çalıştırma:**
  `docker compose run --rm --entrypoint bash wpcli -c "wp --path=/var/www/html --allow-root <komut>"`
  (`wpcli` servisinin varsayılan `entrypoint`'i kurulum betiğidir, elle komut
  çalıştırmak için `--entrypoint bash` ile ezilmesi gerekir.)
- **`wp db query` "TLS/SSL error" verirse:** bu, wp-cli'nin kullandığı
  `mariadb` istemci aracının varsayılan davranışıdır, WordPress'in kendi
  veritabanı bağlantısını (PHP mysqli) etkilemez. Doğrudan sorgu gerekiyorsa
  `wp eval 'global $wpdb; ...'` kullanın veya `--skip-ssl` ekleyin.
