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
		);

		/**
		 * Modül listesini filtreler.
		 *
		 * @param array<string, string> $modules Modül slug => etiket.
		 */
		return apply_filters( 'qmls_modules', $modules );
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
