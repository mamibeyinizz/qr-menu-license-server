<?php
/**
 * Lisans kaydı custom post type ve meta alanları.
 *
 * @package QMLS
 */

defined( 'ABSPATH' ) || exit;

/**
 * qmls_license post type'ını ve meta alanlarını kaydeder.
 */
class QMLS_CPT {

	/**
	 * Post type slug.
	 */
	const POST_TYPE = 'qmls_license';

	/**
	 * API key meta anahtarı.
	 */
	const META_API_KEY = 'api_key';

	/**
	 * Domain meta anahtarı.
	 */
	const META_DOMAIN = 'domain';

	/**
	 * Durum meta anahtarı ("aktif" / "pasif").
	 */
	const META_STATUS = 'durum';

	/**
	 * İzinli modüller meta anahtarı.
	 */
	const META_MODULES = 'allowed_modules';

	/**
	 * Son kullanma tarihi meta anahtarı ("Y-m-d"; boş = süresiz).
	 */
	const META_EXPIRES = 'expires_at';

	/**
	 * Notlar meta anahtarı.
	 */
	const META_NOTES = 'notlar';

	/**
	 * Aktif durum değeri.
	 */
	const STATUS_ACTIVE = 'aktif';

	/**
	 * Pasif durum değeri.
	 */
	const STATUS_INACTIVE = 'pasif';

	/**
	 * Hook'ları bağlar.
	 */
	public function __construct() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
	}

	/**
	 * Lisans post type'ını kaydeder.
	 *
	 * Arayüz tamamen özel ekranlardan yönetildiği için post type menüde
	 * gösterilmez (show_in_menu false), sadece admin tarafında erişilebilir.
	 *
	 * @return void
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => __( 'QR Lisanslar', 'qmls' ),
			'singular_name'      => __( 'Lisans', 'qmls' ),
			'add_new'            => __( 'Yeni Lisans Ekle', 'qmls' ),
			'add_new_item'       => __( 'Yeni Lisans Ekle', 'qmls' ),
			'edit_item'          => __( 'Lisansı Düzenle', 'qmls' ),
			'new_item'           => __( 'Yeni Lisans', 'qmls' ),
			'view_item'          => __( 'Lisansı Görüntüle', 'qmls' ),
			'search_items'       => __( 'Lisans Ara', 'qmls' ),
			'not_found'          => __( 'Lisans bulunamadı', 'qmls' ),
			'not_found_in_trash' => __( 'Çöpte lisans bulunamadı', 'qmls' ),
			'menu_name'          => __( 'QR Lisanslar', 'qmls' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => $labels,
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => false,
				'show_in_rest'    => false,
				'has_archive'     => false,
				'rewrite'         => false,
				'query_var'       => false,
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
				'menu_icon'       => 'dashicons-tickets-alt',
			)
		);
	}

	/**
	 * Lisans meta alanlarını kaydeder.
	 *
	 * @return void
	 */
	public static function register_meta() {
		register_post_meta(
			self::POST_TYPE,
			self::META_API_KEY,
			array(
				'type'              => 'string',
				'description'       => __( 'Lisansa ait benzersiz API key.', 'qmls' ),
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_DOMAIN,
			array(
				'type'              => 'string',
				'description'       => __( 'İlk aktivasyonda kilitlenen domain.', 'qmls' ),
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_STATUS,
			array(
				'type'              => 'string',
				'description'       => __( 'Lisans durumu: aktif veya pasif.', 'qmls' ),
				'single'            => true,
				'default'           => self::STATUS_ACTIVE,
				'show_in_rest'      => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_status' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_MODULES,
			array(
				'type'              => 'array',
				'description'       => __( 'Lisansa tanımlı modül slug listesi.', 'qmls' ),
				'single'            => true,
				'default'           => array(),
				'show_in_rest'      => false,
				'sanitize_callback' => array( 'QMLS_Helpers', 'sanitize_modules' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_EXPIRES,
			array(
				'type'              => 'string',
				'description'       => __( 'Son kullanma tarihi (Y-m-d); boşsa süresiz.', 'qmls' ),
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => array( 'QMLS_Helpers', 'sanitize_expiry_date' ),
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_NOTES,
			array(
				'type'              => 'string',
				'description'       => __( 'Sadece yönetim için tutulan notlar. API cevabına dahil edilmez.', 'qmls' ),
				'single'            => true,
				'default'           => '',
				'show_in_rest'      => false,
				'sanitize_callback' => 'sanitize_textarea_field',
			)
		);
	}

	/**
	 * Durum değerini temizler.
	 *
	 * @param mixed $status Ham durum değeri.
	 * @return string "aktif" veya "pasif".
	 */
	public static function sanitize_status( $status ) {
		return self::STATUS_INACTIVE === $status ? self::STATUS_INACTIVE : self::STATUS_ACTIVE;
	}
}
