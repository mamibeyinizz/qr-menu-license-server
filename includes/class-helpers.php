<?php
/**
 * Yardımcı fonksiyonlar ve modül sabitleri.
 *
 * @package QMLS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Modül listesi, API key üretimi ve lisans sorgulama yardımcıları.
 */
class QMLS_Helpers {

	/**
	 * Sistemdeki sabit modüller: slug => ekranda görünen ad.
	 *
	 * Yeni bir modül eklenecekse SADECE burası değiştirilir; admin ekranındaki
	 * checkbox listesi de kayıt doğrulaması da bu listeyi referans alır.
	 *
	 * @return array<string, string> Modül slug => etiket.
	 */
	public static function get_modules() {
		$modules = array(
			'restoran-menu'             => __( 'Restoran Menü', 'qmls' ),
			'yorum-feedback'            => __( 'Yorum & Feedback', 'qmls' ),
			'qr-masa'                   => __( 'QR Masa', 'qmls' ),
			'qr-analiz'                 => __( 'QR Analiz', 'qmls' ),
			'qr-galeri'                 => __( 'QR Galeri', 'qmls' ),
			'qr-ceviri'                 => __( 'QR Çeviri', 'qmls' ),
			'qr-chatbot'                => __( 'QR Chatbot', 'qmls' ),
			'qr-calisma-saatleri'       => __( 'QR Çalışma Saatleri', 'qmls' ),
			'qr-masa-oturum-guvenligi'  => __( 'QR Masa Oturum Güvenliği', 'qmls' ),
			'qr-acilis-ekrani'          => __( 'Açılış Ekranı', 'qmls' ),
			'header-footer-builder'     => __( 'Header Footer Builder', 'qmls' ),
			'qr-menu-muhendisligi'      => __( 'Menü Mühendisliği', 'qmls' ),
			'qr-servis-paneli'          => __( 'Servis Paneli', 'qmls' ),
		);

		/**
		 * Modül listesini filtreler.
		 *
		 * @param array<string, string> $modules Modül slug => etiket.
		 */
		return apply_filters( 'qmls_modules', $modules );
	}

	/**
	 * Paket şablonlarının kaydedildiği option anahtarı.
	 */
	const OPTION_TIERS = 'qmls_tiers';

	/**
	 * Paket şablonlarının fabrika ayarları: slug => (etiket, modül slug listesi).
	 *
	 * Sadece admin hiç özelleştirme yapmadıysa ya da bir paketi sıfırlarsa
	 * kullanılır; slug'lar sabittir, "Paketler" ekranından yalnızca etiket ve
	 * modül içeriği değiştirilebilir.
	 *
	 * @return array<string, array{label:string, modules:string[]}> Paket slug => tanım.
	 */
	public static function get_default_tiers() {
		return array(
			'baslangic' => array(
				'label'   => __( 'Başlangıç', 'qmls' ),
				'modules' => array(
					'restoran-menu',
					'qr-masa',
					'yorum-feedback',
				),
			),
			'pro'       => array(
				'label'   => __( 'Pro', 'qmls' ),
				'modules' => array(
					'restoran-menu',
					'qr-masa',
					'yorum-feedback',
					'qr-analiz',
					'qr-galeri',
					'qr-ceviri',
					'qr-calisma-saatleri',
					'qr-acilis-ekrani',
					'header-footer-builder',
				),
			),
			'kurumsal'  => array(
				'label'   => __( 'Kurumsal', 'qmls' ),
				'modules' => self::get_module_slugs(),
			),
		);
	}

	/**
	 * Hazır paket şablonları: slug => (etiket, modül slug listesi).
	 *
	 * "Paketler" ekranından kaydedilen özelleştirmeler fabrika ayarlarının
	 * üzerine yazılır; hiç özelleştirme yoksa fabrika ayarları döner.
	 * Admin formunda modül checkbox'larını tek tıkla doldurmak için
	 * kullanılır; lisans kaydında ayrı bir "paket" alanı tutulmaz, seçim
	 * sonrası yine mevcut allowed_modules listesi kaydedilir.
	 *
	 * @return array<string, array{label:string, modules:string[]}> Paket slug => tanım.
	 */
	public static function get_tiers() {
		$tiers = self::get_default_tiers();
		$saved = get_option( self::OPTION_TIERS, array() );

		if ( is_array( $saved ) ) {
			foreach ( $tiers as $slug => $default ) {
				if ( ! isset( $saved[ $slug ] ) || ! is_array( $saved[ $slug ] ) ) {
					continue;
				}

				if ( ! empty( $saved[ $slug ]['label'] ) ) {
					$tiers[ $slug ]['label'] = sanitize_text_field( (string) $saved[ $slug ]['label'] );
				}

				if ( isset( $saved[ $slug ]['modules'] ) ) {
					$tiers[ $slug ]['modules'] = self::sanitize_modules( $saved[ $slug ]['modules'] );
				}
			}
		}

		/**
		 * Paket şablonlarını filtreler.
		 *
		 * @param array<string, array{label:string, modules:string[]}> $tiers Paket slug => tanım.
		 */
		return apply_filters( 'qmls_tiers', $tiers );
	}

	/**
	 * Paket şablonu özelleştirmelerini kaydeder.
	 *
	 * Slug'lar sabittir (get_default_tiers() ile aynı küme); gelen veri bu
	 * slug'lara indirgenir, tanınmayan slug'lar yok sayılır.
	 *
	 * @param array<string, array{label?:string, modules?:mixed}> $tiers Ham paket verisi.
	 * @return void
	 */
	public static function save_tiers( $tiers ) {
		$tiers = is_array( $tiers ) ? $tiers : array();
		$clean = array();

		foreach ( self::get_default_tiers() as $slug => $default ) {
			$label   = isset( $tiers[ $slug ]['label'] ) ? sanitize_text_field( (string) $tiers[ $slug ]['label'] ) : '';
			$modules = isset( $tiers[ $slug ]['modules'] ) ? self::sanitize_modules( $tiers[ $slug ]['modules'] ) : array();

			$clean[ $slug ] = array(
				'label'   => '' !== $label ? $label : $default['label'],
				'modules' => $modules,
			);
		}

		update_option( self::OPTION_TIERS, $clean );
	}

	/**
	 * Geçerli modül slug'larını döndürür.
	 *
	 * @return string[] Modül slug listesi.
	 */
	public static function get_module_slugs() {
		return array_keys( self::get_modules() );
	}

	/**
	 * Gelen modül dizisini temizler; sadece bilinen slug'ları bırakır.
	 *
	 * @param mixed $modules Ham modül listesi.
	 * @return string[] Temizlenmiş modül slug listesi.
	 */
	public static function sanitize_modules( $modules ) {
		if ( ! is_array( $modules ) ) {
			return array();
		}

		$modules = array_map( 'sanitize_text_field', array_map( 'strval', $modules ) );
		$modules = array_values( array_intersect( self::get_module_slugs(), $modules ) );

		return $modules;
	}

	/**
	 * Bir lisans kaydının modül listesini döndürür.
	 *
	 * @param int $license_id Lisans post ID.
	 * @return string[] Modül slug listesi.
	 */
	public static function get_license_modules( $license_id ) {
		$modules = get_post_meta( $license_id, QMLS_CPT::META_MODULES, true );

		return self::sanitize_modules( $modules );
	}

	/**
	 * Bir tarih değerini temizler (son kullanma ve başlangıç tarihi için ortak).
	 *
	 * @param mixed $date Ham tarih değeri.
	 * @return string Geçerli "Y-m-d" tarihi ya da boş string.
	 */
	public static function sanitize_date( $date ) {
		$date = sanitize_text_field( (string) $date );

		if ( '' === $date ) {
			return '';
		}

		$parsed = DateTime::createFromFormat( 'Y-m-d', $date );

		if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
			return '';
		}

		return $date;
	}

	/**
	 * Bir lisansın süresi dolmuş mu?
	 *
	 * Son kullanma tarihi tanımlı değilse lisans süresiz kabul edilir.
	 * Tanımlıysa o günün sonuna kadar geçerlidir.
	 *
	 * @param int $license_id Lisans post ID.
	 * @return bool Süresi dolmuş mu.
	 */
	public static function is_expired( $license_id ) {
		$expires = self::sanitize_date( get_post_meta( $license_id, QMLS_CPT::META_EXPIRES, true ) );

		if ( '' === $expires ) {
			return false;
		}

		$expires_ts = strtotime( $expires . ' 23:59:59' );

		return false !== $expires_ts && $expires_ts < time();
	}

	/**
	 * Bir lisansın süresi yakında (varsayılan 7 gün içinde) mi doluyor?
	 *
	 * Süresi zaten dolmuş ya da süresiz lisanslar için false döner; sadece
	 * "henüz aktif ama yakında dolacak" durumunu yakalar.
	 *
	 * @param int $license_id Lisans post ID.
	 * @param int $days       Eşik gün sayısı.
	 * @return bool Yakında dolacak mı.
	 */
	public static function is_expiring_soon( $license_id, $days = 7 ) {
		$expires = self::sanitize_date( get_post_meta( $license_id, QMLS_CPT::META_EXPIRES, true ) );

		if ( '' === $expires ) {
			return false;
		}

		$expires_ts = strtotime( $expires . ' 23:59:59' );

		if ( false === $expires_ts || $expires_ts < time() ) {
			return false;
		}

		return $expires_ts <= time() + ( $days * DAY_IN_SECONDS );
	}

	/**
	 * Son kullanma tarihine kalan tam gün sayısı.
	 *
	 * @param int $license_id Lisans post ID.
	 * @return int|null Süresiz ya da tarih geçersizse null; aksi halde gün sayısı (negatifse süre dolmuş).
	 */
	public static function get_days_until_expiry( $license_id ) {
		$expires = self::sanitize_date( get_post_meta( $license_id, QMLS_CPT::META_EXPIRES, true ) );

		if ( '' === $expires ) {
			return null;
		}

		$expires_ts = strtotime( $expires . ' 23:59:59' );

		if ( false === $expires_ts ) {
			return null;
		}

		return (int) ceil( ( $expires_ts - time() ) / DAY_IN_SECONDS );
	}

	/**
	 * Bir lisans için tutulan azami aktivite kaydı sayısı.
	 */
	const AUDIT_LOG_MAX = 50;

	/**
	 * Aktivite kaydı işlem kodlarının okunabilir Türkçe karşılığı.
	 *
	 * @return array<string, string> İşlem kodu => etiket.
	 */
	public static function get_audit_action_labels() {
		return array(
			'olusturuldu'              => __( 'Lisans oluşturuldu', 'qmls' ),
			'guncellendi'              => __( 'Lisans güncellendi', 'qmls' ),
			'domain_kilitlendi'        => __( 'Domain otomatik kilitlendi', 'qmls' ),
			'domain_kilidi_kaldirildi' => __( 'Domain kilidi kaldırıldı', 'qmls' ),
			'api_key_yenilendi'        => __( 'API key yenilendi', 'qmls' ),
			'surum_guncellendi'        => __( 'İstemci sürümü değişti', 'qmls' ),
			'silindi'                  => __( 'Çöpe taşındı', 'qmls' ),
		);
	}

	/**
	 * Bir işlem koduna karşılık gelen okunabilir etiketi döndürür.
	 *
	 * @param string $action İşlem kodu.
	 * @return string Etiket; tanınmayan kodlar olduğu gibi döner.
	 */
	public static function get_audit_action_label( $action ) {
		$labels = self::get_audit_action_labels();

		return isset( $labels[ $action ] ) ? $labels[ $action ] : $action;
	}

	/**
	 * Bir lisansa aktivite kaydı ekler.
	 *
	 * Sadece son AUDIT_LOG_MAX kayıt tutulur; eskiler sessizce düşer.
	 *
	 * @param int    $license_id Lisans post ID.
	 * @param string $action     İşlem kodu (bkz. get_audit_action_labels()).
	 * @param string $detail     Opsiyonel kısa açıklama (ör. domain adı).
	 * @return void
	 */
	public static function add_audit_entry( $license_id, $action, $detail = '' ) {
		$log = get_post_meta( $license_id, QMLS_CPT::META_AUDIT_LOG, true );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		$user = wp_get_current_user();

		$log[] = array(
			'time'   => time(),
			'user'   => ( $user && $user->exists() ) ? $user->user_login : __( 'sistem', 'qmls' ),
			'action' => sanitize_text_field( $action ),
			'detail' => sanitize_text_field( $detail ),
		);

		if ( count( $log ) > self::AUDIT_LOG_MAX ) {
			$log = array_slice( $log, -self::AUDIT_LOG_MAX );
		}

		update_post_meta( $license_id, QMLS_CPT::META_AUDIT_LOG, $log );
	}

	/**
	 * Bir lisansın aktivite kaydını en yeniden en eskiye döndürür.
	 *
	 * @param int $license_id Lisans post ID.
	 * @return array<int, array{time:int,user:string,action:string,detail:string}> Kayıt listesi.
	 */
	public static function get_audit_log( $license_id ) {
		$log = get_post_meta( $license_id, QMLS_CPT::META_AUDIT_LOG, true );

		if ( ! is_array( $log ) ) {
			return array();
		}

		return array_reverse( $log );
	}

	/**
	 * Benzersiz bir API key üretir (40 karakterlik hex).
	 *
	 * @return string API key.
	 */
	public static function generate_api_key() {
		do {
			$key = bin2hex( random_bytes( 20 ) );
		} while ( null !== self::get_license_by_api_key( $key ) );

		return $key;
	}

	/**
	 * API key ile eşleşen lisans kaydını bulur.
	 *
	 * @param string $api_key Aranacak API key.
	 * @return WP_Post|null Bulunan lisans postu ya da null.
	 */
	public static function get_license_by_api_key( $api_key ) {
		$api_key = sanitize_text_field( $api_key );

		if ( '' === $api_key ) {
			return null;
		}

		$query = new WP_Query(
			array(
				'post_type'              => QMLS_CPT::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- API key ile tekil kayıt araması gerekiyor.
				'meta_query'             => array(
					array(
						'key'     => QMLS_CPT::META_API_KEY,
						'value'   => $api_key,
						'compare' => '=',
					),
				),
			)
		);

		if ( empty( $query->posts ) ) {
			return null;
		}

		return $query->posts[0];
	}

	/**
	 * Domain değerini karşılaştırmaya uygun hale getirir.
	 *
	 * Protokol, "www." öneki, port, yol ve büyük/küçük harf farkları temizlenir.
	 *
	 * @param string $domain Ham domain değeri.
	 * @return string Normalize edilmiş domain.
	 */
	public static function normalize_domain( $domain ) {
		$domain = sanitize_text_field( (string) $domain );
		$domain = trim( $domain );

		if ( '' === $domain ) {
			return '';
		}

		// Protokol varsa host kısmını al.
		if ( false !== strpos( $domain, '://' ) ) {
			$host = wp_parse_url( $domain, PHP_URL_HOST );

			if ( ! empty( $host ) ) {
				$domain = $host;
			}
		}

		$domain = strtolower( $domain );
		$domain = preg_replace( '#^www\.#', '', $domain );
		// Port, yol, query ve fragment kısımlarını at.
		$domain = preg_replace( '#[/:?\#].*$#', '', $domain );
		$domain = trim( (string) $domain, '.' );

		return $domain;
	}
}

/**
 * Benzersiz bir API key üretir.
 *
 * @return string 40 karakterlik hex API key.
 */
function qmls_generate_api_key() {
	return QMLS_Helpers::generate_api_key();
}

/**
 * Sistemdeki modül listesini döndürür.
 *
 * @return array<string, string> Modül slug => etiket.
 */
function qmls_get_modules() {
	return QMLS_Helpers::get_modules();
}
