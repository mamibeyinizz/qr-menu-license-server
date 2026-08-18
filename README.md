# QR Menu License Server

WordPress plugin'i. qrmenuofficial.com üzerinde çalışır; müşteri sitelerine kurulan
"QR Menü" plugin'inin API key doğrulaması yapmasını ve o key'e tanımlı modül listesini
JSON olarak almasını sağlar.

Satış/ödeme otomasyonu yoktur — her lisans kaydı site admini tarafından elle oluşturulur.

## Kurulum

1. `qr-menu-license-server` klasörünü `wp-content/plugins/` altına kopyalayın.
2. WordPress admin > Eklentiler ekranından **QR Menu License Server**'ı etkinleştirin.
3. Sol menüde **QR Lisanslar** başlığı görünecektir.

Gereksinimler: WordPress 6.0+, PHP 7.4+. Harici bağımlılık (composer paketi vb.) yoktur.

## Dosya yapısı

```
qr-menu-license-server/
  qr-menu-license-server.php   ana dosya, plugin header, hook'lar
  includes/
    class-cpt.php              qmls_license post type + meta alanları
    class-admin.php            admin menü, kart listesi, lisans formu
    class-rest-api.php         /qmls/v1/validate endpoint'i
    class-helpers.php          API key üretimi, modül sabitleri, domain normalize
  assets/
    css/admin.css              mobil öncelikli stiller
    js/admin.js                kopyala butonu, onay diyalogları
```

## Lisans kaydı

Post type: `qmls_license` (public değil, sadece admin). Meta alanları:

| Meta | Tip | Açıklama |
| --- | --- | --- |
| `api_key` | string | Kayıt oluşturulurken otomatik üretilir, 40 karakter hex, benzersiz |
| `domain` | string | İlk başarılı doğrulamada otomatik yazılır (domain-lock) |
| `durum` | string | `aktif` veya `pasif` |
| `allowed_modules` | array | Seçili modül slug'ları |
| `notlar` | string | Sadece yönetim içindir, API cevabına **dahil edilmez** |

## Modüller

Modül listesi tek yerde tanımlıdır: `QMLS_Helpers::get_modules()` (`includes/class-helpers.php`).
Yeni modül eklemek için sadece bu diziye satır eklemek yeterlidir; admin ekranındaki
checkbox listesi ve API doğrulaması aynı listeyi kullanır. Dışarıdan eklemek için
`qmls_modules` filtresi de kullanılabilir.

Mevcut modüller: `restoran-menu`, `yorum-feedback`, `qr-masa`, `qr-analiz`, `qr-galeri`,
`qr-ceviri`, `qr-chatbot`, `qr-calisma-saatleri`, `qr-masa-oturum-guvenligi`.

## Admin arayüzü

Arayüz mobil önceliklidir: tablo yoktur, her lisans dikey bir karttır, tüm dokunma
hedefleri en az 44px yüksekliktedir.

- **QR Lisanslar** (liste): firma adı, durum rozeti, domain, "X/9 modül aktif" bilgisi.
  Üstte tam genişlikte "+ Yeni Lisans Ekle" butonu; karta dokununca düzenleme ekranı açılır.
- **Düzenleme ekranı**: tek sütun form — Firma Adı → Modül seçimi (9 adet, büyük satırlar)
  → Durum toggle → Notlar. Kaydet butonu ekranın altında sabittir (sticky).
  Kayıttan sonra API key büyük bir kutuda ve tek dokunuşluk "Kopyala" butonuyla gösterilir.
- **Domain** alanı salt okunurdur: "Kayıtlı domain: X" ya da "Henüz aktifleşmedi".
  "Domain kilidini kaldır" butonu onay sorup `domain` meta'sını boşaltır; bir sonraki
  doğrulamada yeni domain kaydedilir.

## REST API

```
POST /wp-json/qmls/v1/validate
Content-Type: application/json

{ "api_key": "…40 karakter hex…", "domain": "musteri-sitesi.com" }
```

Yanıtlar:

| Durum | HTTP | Body |
| --- | --- | --- |
| Key bulunamadı | 404 | `{ "status": "invalid" }` |
| Lisans pasif | 200 | `{ "status": "inactive" }` |
| Domain uyuşmuyor | 200 | `{ "status": "domain_mismatch", "modules": [] }` |
| Geçerli | 200 | `{ "status": "active", "modules": ["restoran-menu", …] }` |

Notlar:

- Endpoint public'tir; nonce/auth header gerekmez, koruma api_key'in kendisidir.
- Kayıtta domain boşsa ilk gelen domain yazılır (domain-lock) ve yanıt `active` olur.
- Domain karşılaştırması normalize edilerek yapılır: protokol, `www.`, port, yol ve
  büyük/küçük harf farkları göz ardı edilir (`https://www.Site.com/menu` → `site.com`).
- `domain_mismatch` durumunda modül listesi boş döner; plugin'i kilitleme kararı
  müşteri tarafındaki plugin'e aittir.
- Sorgular `WP_Query` + `meta_query` ile yapılır, ham SQL yoktur; girdiler
  `sanitize_text_field` ile temizlenir.

### Müşteri plugin'i tarafından örnek çağrı

```php
$response = wp_remote_post(
	'https://qrmenuofficial.com/wp-json/qmls/v1/validate',
	array(
		'timeout' => 15,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode(
			array(
				'api_key' => get_option( 'qr_menu_api_key' ),
				'domain'  => wp_parse_url( home_url(), PHP_URL_HOST ),
			)
		),
	)
);

$data = json_decode( wp_remote_retrieve_body( $response ), true );
// $data['status'] => active | inactive | domain_mismatch | invalid
// $data['modules'] => izinli modül slug listesi
```

## Manuel test

Plugin'i etkinleştirdikten sonra:

1. **QR Lisanslar > Yeni Ekle** ile bir test lisansı oluşturun (2-3 modül seçili, durum Aktif).
   Kaydedince API key kutusu görünür, "Kopyala" ile alın.
2. Doğru key + bir domain ile çağırın → `{"status":"active","modules":[…]}` dönmeli ve
   düzenleme ekranında "Kayıtlı domain: …" yazmalı:

   ```js
   fetch( '/wp-json/qmls/v1/validate', {
     method: 'POST',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify( { api_key: 'BURAYA_KEY', domain: 'test-musteri.com' } )
   } ).then( r => r.json().then( j => console.log( r.status, j ) ) );
   ```

3. Yanlış key ile çağırın → HTTP 404, `{"status":"invalid"}`.
4. Aynı key'i farklı bir domain ile çağırın → `{"status":"domain_mismatch","modules":[]}`.
5. Lisansı Pasif yapıp tekrar çağırın → `{"status":"inactive"}`.
6. "Domain kilidini kaldır" deyip yeni bir domain ile çağırın → tekrar `active` dönmeli ve
   yeni domain kilitlenmeli.
