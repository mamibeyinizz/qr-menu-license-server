<?php
/**
 * Lisans doğrulama REST endpoint'i.
 *
 * @package QMLS
 */

defined( 'ABSPATH' ) || exit;

/**
 * /wp-json/qmls/v1/validate endpoint'ini kaydeder ve yanıtlar.
 */
class QMLS_REST_API {

	/**
	 * REST namespace.
	 */
	const NAMESPACE_V1 = 'qmls/v1';

	/**
	 * Endpoint yolu.
	 */
	const ROUTE_VALIDATE = '/validate';

	/**
	 * Tekil örnek.
	 *
	 * @var QMLS_REST_API|null
	 */
	private static $instance = null;

	/**
	 * Tekil örneği döndürür.
	 *
	 * @return QMLS_REST_API
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Route kaydını bağlar.
	 *
	 * Ana dosyadan, plugin yüklenirken (plugins_loaded beklemeden) çağrılır:
	 * endpoint'in varlığı başka bir bileşenin başlatılmasına bağlı kalmasın.
	 * Aynı nesne/metot çifti WordPress tarafından tek kez kaydedilir, bu yüzden
	 * birden fazla çağrı route'u çiftlemez.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( self::instance(), 'register_routes' ) );
	}

	/**
	 * Doğrulama endpoint'inin tam adresi.
	 *
	 * @return string
	 */
	public static function get_endpoint_url() {
		return rest_url( self::NAMESPACE_V1 . self::ROUTE_VALIDATE );
	}

	/**
	 * Route bu istekte REST sunucusuna kayıtlı mı?
	 *
	 * Teşhis amaçlıdır: admin ekranında endpoint'in gerçekten kayıtlı olup
	 * olmadığını göstermek için kullanılır.
	 *
	 * @return bool
	 */
	public static function is_route_registered() {
		if ( ! function_exists( 'rest_get_server' ) ) {
			return false;
		}

		$routes = rest_get_server()->get_routes( self::NAMESPACE_V1 );

		return isset( $routes[ '/' . self::NAMESPACE_V1 . self::ROUTE_VALIDATE ] );
	}

	/**
	 * Route'ları kaydeder.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			self::ROUTE_VALIDATE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate' ),
				// Public endpoint: sadece api_key ile korunur.
				'permission_callback' => '__return_true',
				'args'                => array(
					'api_key' => array(
						'type'              => 'string',
						'required'          => true,
						'description'       => __( 'Lisansa ait API key.', 'qmls' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
					'domain'  => array(
						'type'              => 'string',
						'required'          => true,
						'description'       => __( 'İsteği yapan sitenin domaini.', 'qmls' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * API key ve domain doğrulaması yapar.
	 *
	 * @param WP_REST_Request $request İstek nesnesi.
	 * @return WP_REST_Response JSON yanıtı.
	 */
	public function validate( WP_REST_Request $request ) {
		$api_key = (string) $request->get_param( 'api_key' );
		$domain  = QMLS_Helpers::normalize_domain( (string) $request->get_param( 'domain' ) );

		$license = QMLS_Helpers::get_license_by_api_key( $api_key );

		if ( null === $license ) {
			return new WP_REST_Response( array( 'status' => 'invalid' ), 404 );
		}

		$status = QMLS_CPT::sanitize_status( get_post_meta( $license->ID, QMLS_CPT::META_STATUS, true ) );

		if ( QMLS_CPT::STATUS_ACTIVE !== $status ) {
			return new WP_REST_Response( array( 'status' => 'inactive' ), 200 );
		}

		$saved_domain = QMLS_Helpers::normalize_domain( get_post_meta( $license->ID, QMLS_CPT::META_DOMAIN, true ) );

		if ( '' === $saved_domain ) {
			// İlk aktivasyon: gelen domain kayda yazılır (domain-lock).
			if ( '' === $domain ) {
				return new WP_REST_Response(
					array(
						'status'  => 'domain_mismatch',
						'modules' => array(),
					),
					200
				);
			}

			update_post_meta( $license->ID, QMLS_CPT::META_DOMAIN, $domain );
			$saved_domain = $domain;
		}

		if ( $saved_domain !== $domain ) {
			return new WP_REST_Response(
				array(
					'status'  => 'domain_mismatch',
					'modules' => array(),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'status'  => 'active',
				'modules' => QMLS_Helpers::get_license_modules( $license->ID ),
			),
			200
		);
	}
}
