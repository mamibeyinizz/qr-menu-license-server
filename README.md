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
  tools/
    qmls-diagnostic.php        bağımsız teşhis aracı (plugin tarafından yüklenmez)
```

## Lisans kaydı

Post type: `qmls_license` (public değil, sadece admin). Meta alanları:

| Meta | Tip | Açıklama |
| --- | --- | --- |
| `api_key` | string | Kayıt oluşturulurken otomatik üretilir, 40 karakter hex, benzersiz |
| `domain` | string | İlk başarılı doğrulamada otomatik yazılır (domain-lock) |
| `durum` | string | `aktif` veya `pasif` |
| `allowed_modules` | array | Seçili modül slug'ları |
| `expires_at` | string | Son kullanma tarihi (`Y-m-d`); boşsa süresiz |
| `start_date` | string | Başlangıç tarihi (`Y-m-d`); opsiyonel, sadece takip amaçlı |
| `amount_paid` | string | Alınan ücret, serbest metin (ör. "1500 TL/yıl") |
| `notlar` | string | Sadece yönetim içindir, API cevabına **dahil edilmez** |
| `last_seen` | integer | İstemcinin son doğrulama zamanı (unix timestamp) |
| `client_version` | string | İstemcinin en son bildirdiği plugin sürümü |
| `_qmls_audit_log` | array | Son 50 işlemin kaydı (oluşturma, güncelleme, domain kilidi, key yenileme, silme, sürüm değişimi) |

## Modüller

Modül listesi tek yerde tanımlıdır: `QMLS_Helpers::get_modules()` (`includes/class-helpers.php`).
Yeni modül eklemek için sadece bu diziye satır eklemek yeterlidir; admin ekranındaki
checkbox listesi ve API doğrulaması aynı listeyi kullanır. Dışarıdan eklemek için
`qmls_modules` filtresi de kullanılabilir.

Mevcut modüller: `restoran-menu`, `yorum-feedback`, `qr-masa`, `qr-analiz`, `qr-galeri`,
`qr-ceviri`, `qr-chatbot`, `qr-calisma-saatleri`, `qr-masa-oturum-guvenligi`,
`qr-acilis-ekrani`, `header-footer-builder`, `qr-menu-muhendisligi`, `qr-servis-paneli`.

## Paket şablonları

**QR Lisanslar > Paketler** ekranından Başlangıç/Pro/Kurumsal paketlerinin adı ve
içerdiği modüller elle düzenlenebilir (`QMLS_Helpers::get_tiers()` / `save_tiers()`,
`qmls_tiers` option'ında saklanır). Düzenleme ekranındaki paket butonları buradaki
güncel tanımı kullanır; bir pakete tıklamak sadece modül checkbox'larını doldurur,
kaydetmeden önce elle değiştirilebilir. Hiç özelleştirme yapılmadıysa fabrika
ayarları (`get_default_tiers()`) geçerlidir.

## Admin arayüzü

Arayüz mobil önceliklidir: tablo yoktur, her lisans dikey bir karttır, tüm dokunma
hedefleri en az 44px yüksekliktedir.

- **QR Lisanslar** (liste): üstte toplam/aktif/yakında dolacak (7 gün)/süresi dolmuş
  sayılarını gösteren bir özet şeridi, ardından her lisans için bir kart — firma adı,
  durum/süre rozeti (aktif, pasif, "X gün kaldı" ya da "süresi doldu"), domain,
  "X/13 modül aktif", varsa alınan ücret ve son görülme bilgisi. Süresi dolmuş ve
  yakında dolacak lisanslar listenin başına alınır. "+ Yeni Lisans Ekle", "Paketleri
  Düzenle" ve "CSV olarak dışa aktar" butonları üstte sabittir.
- **Düzenleme ekranı**: tek sütun form — Firma Adı → paket şablonu butonları → Modül
  seçimi → Durum toggle → Başlangıç tarihi → Son kullanma tarihi → Alınan ücret →
  Notlar. Kaydet butonu ekranın altında sabittir (sticky). Kayıttan sonra API key
  büyük bir kutuda ve tek dokunuşluk "Kopyala" butonuyla gösterilir. Altta "Domain
  kilidini kaldır", "API key'i yeniden üret", "Lisansı sil" butonları ve son 50
  işlemi listeleyen "Aktivite geçmişi" bulunur.
- **Domain** alanı salt okunurdur: "Kayıtlı domain: X" ya da "Henüz aktifleşmedi".
  "Domain kilidini kaldır" butonu onay sorup `domain` meta'sını boşaltır; bir sonraki
  doğrulamada yeni domain kaydedilir.
- **Paketler**: Başlangıç/Pro/Kurumsal için ayrı ayrı paket adı ve modül listesi
  düzenlenip tek seferde kaydedilir.

## REST API

```
POST /wp-json/qmls/v1/validate
Content-Type: application/json

{ "api_key": "…40 karakter hex…", "domain": "musteri-sitesi.com", "version": "1.1.0" }
```

`version` opsiyoneldir; gönderilirse `client_version` meta'sında saklanır ve
değiştiğinde aktivite geçmişine not düşülür.

Yanıtlar:

| Durum | HTTP | Body |
| --- | --- | --- |
| Key bulunamadı | 404 | `{ "status": "invalid" }` |
| Lisans pasif | 200 | `{ "status": "inactive" }` |
| Süresi dolmuş | 200 | `{ "status": "expired", "modules": [] }` |
| Domain uyuşmuyor | 200 | `{ "status": "domain_mismatch", "modules": [] }` |
| Geçerli | 200 | `{ "status": "active", "modules": ["restoran-menu", …] }` |
| Hız sınırı aşıldı | 429 | `{ "status": "rate_limited" }` (`Retry-After` header'ı ile) |

Notlar:

- Endpoint public'tir; nonce/auth header gerekmez, koruma api_key'in kendisidir.
- IP başına dakikada 20 istekle sınırlıdır; aşımda 429 + `Retry-After` döner.
- Kayıtta domain boşsa ilk gelen domain yazılır (domain-lock, atomik bir SQL UPDATE
  ile yarış durumuna karşı korumalı) ve yanıt `active` olur.
- Domain karşılaştırması normalize edilerek yapılır: protokol, `www.`, port, yol ve
  büyük/küçük harf farkları göz ardı edilir (`https://www.Site.com/menu` → `site.com`).
- `domain_mismatch` ve `expired` durumlarında modül listesi boş döner; plugin'i
  kilitleme kararı müşteri tarafındaki plugin'e aittir.
- Sorgular `WP_Query` + `meta_query` ile yapılır, ham SQL yoktur (domain-lock yazımı
  hariç — o da parametreli/prepared bir sorgudur); girdiler `sanitize_text_field`
  ile temizlenir.

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

## Endpoint görünmüyorsa: teşhis

`/wp-json/` çıktısındaki `namespaces` listesinde `qmls/v1` yoksa, sorunun kodda mı
sunucuda mı olduğunu ayırt etmek için `tools/qmls-diagnostic.php` dosyasını sunucuda
şu konuma kopyalayın:

```
wp-content/mu-plugins/qmls-diagnostic.php
```

`mu-plugins` klasöründeki dosyalar her zaman yüklenir; bu yüzden rapor, plugin hiç
yüklenmese bile çalışır. Ardından **Araçlar → QMLS Teşhis** ekranını açın. Rapor şunları
gösterir:

- plugin klasöründeki her dosya: var mı, boyutu, değiştirilme tarihi, md5 özeti
  (sunucudaki kopyanın depodakiyle aynı olup olmadığını buradan anlarsınız),
- plugin `active_plugins` içinde mi, ağ genelinde mi aktif,
- `QMLS_VERSION` sabiti ve dört sınıfın gerçekten yüklenip yüklenmediği,
- `/qmls/v1/validate` route'unun REST sunucusuna kayıtlı olup olmadığı,
- REST'e müdahale eden filtrelere (`rest_endpoints`, `rest_pre_dispatch`,
  `rest_authentication_errors`) bağlı callback isimleri — tanımadığınız bir isim,
  route'u gizleyen eklentiyi işaret eder,
- genel `/wp-json/` indeksinin ve endpoint'in canlı test sonucu,
- `wp-content/debug.log` içindeki ilgili hata satırları.

Yorumlama:

| Rapor | Anlamı |
| --- | --- |
| Dosyalar ✗ veya md5'ler depodakinden farklı | Sunucudaki kopya eksik/eski: dosyaları yeniden yükleyin |
| `active_plugins içinde` ✗ | Plugin aktif değil: Eklentiler ekranından etkinleştirin |
| Sınıflar ✗ | Dosyalar var ama yüklenmiyor: hata kaydı bölümüne bakın |
| Route ✓ ama indekste `qmls/v1` yok | Route kayıtlı, dışarıya sızdıran katman engelliyor: önbellek (LiteSpeed → Purge All) veya REST'i kısıtlayan güvenlik eklentisi |
| Endpoint POST testi ✓ (`{"status":"invalid"}`) | Endpoint sağlıklı çalışıyor |

Teşhis bitince dosyayı silin; yalnızca okuma yapar, hiçbir ayarı değiştirmez.
