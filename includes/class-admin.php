<?php
/**
 * Mobil öncelikli admin arayüzü.
 *
 * @package QMLS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lisans listesi ve lisans formu ekranlarını yönetir.
 */
class QMLS_Admin {

	/**
	 * Liste ekranı sayfa slug'ı.
	 */
	const PAGE_LIST = 'qmls-licenses';

	/**
	 * Ekleme/düzenleme ekranı sayfa slug'ı.
	 */
	const PAGE_EDIT = 'qmls-license-edit';

	/**
	 * Gerekli yetki.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Üst menünün konumu.
	 *
	 * WordPress menü satırlarını $menu dizisinde konumu anahtar yaparak tutar
	 * ($menu['26']). Aynı tam sayı konumunu kullanan başka bir plugin (ör. eski
	 * QR Menu Official de 26'yı kullanıyor) o slotu ezdiğinde menü hiç
	 * görünmez. Ondalıklı ve bize özgü bir konum bu çakışmayı önler.
	 *
	 * @var float
	 */
	const MENU_POSITION = 58.2;

	/**
	 * Hook'ları bağlar.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_qmls_save_license', array( $this, 'handle_save' ) );
		add_action( 'admin_post_qmls_unlock_domain', array( $this, 'handle_unlock_domain' ) );
		add_action( 'admin_post_qmls_delete_license', array( $this, 'handle_delete' ) );
	}

	/**
	 * Admin menüsünü kaydeder.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'QR Lisanslar', 'qmls' ),
			__( 'QR Lisanslar', 'qmls' ),
			self::CAPABILITY,
			self::PAGE_LIST,
			array( $this, 'render_list_page' ),
			'dashicons-tickets-alt',
			self::MENU_POSITION
		);

		add_submenu_page(
			self::PAGE_LIST,
			__( 'Tüm Lisanslar', 'qmls' ),
			__( 'Tüm Lisanslar', 'qmls' ),
			self::CAPABILITY,
			self::PAGE_LIST,
			array( $this, 'render_list_page' )
		);

		add_submenu_page(
			self::PAGE_LIST,
			__( 'Yeni Lisans Ekle', 'qmls' ),
			__( 'Yeni Ekle', 'qmls' ),
			self::CAPABILITY,
			self::PAGE_EDIT,
			array( $this, 'render_edit_page' )
		);
	}

	/**
	 * Sadece plugin ekranlarında CSS/JS yükler.
	 *
	 * @param string $hook_suffix Geçerli admin sayfası hook'u.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! $this->is_plugin_screen( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			'qmls-admin',
			QMLS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			QMLS_VERSION
		);

		wp_enqueue_script(
			'qmls-admin',
			QMLS_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			QMLS_VERSION,
			true
		);

		wp_localize_script(
			'qmls-admin',
			'qmlsAdmin',
			array(
				'copied'        => __( 'Kopyalandı!', 'qmls' ),
				'copy'          => __( 'Kopyala', 'qmls' ),
				'copyFailed'    => __( 'Kopyalanamadı, key\'i elle seçip kopyalayın.', 'qmls' ),
				'confirmUnlock' => __( 'Domain kilidi kaldırılsın mı? Müşteri plugin\'i bir sonraki doğrulamada hangi domainde çalışıyorsa oraya kilitlenir.', 'qmls' ),
				'confirmDelete' => __( 'Bu lisans çöpe taşınsın mı?', 'qmls' ),
			)
		);
	}

	/**
	 * Geçerli ekranın plugin ekranlarından biri olup olmadığını kontrol eder.
	 *
	 * @param string $hook_suffix Geçerli admin sayfası hook'u.
	 * @return bool Plugin ekranı mı.
	 */
	private function is_plugin_screen( $hook_suffix ) {
		return (
			false !== strpos( $hook_suffix, self::PAGE_LIST ) ||
			false !== strpos( $hook_suffix, self::PAGE_EDIT )
		);
	}

	/**
	 * Liste ekranının URL'ini üretir.
	 *
	 * @param array<string, string|int> $args Ek query argümanları.
	 * @return string URL.
	 */
	public static function get_list_url( $args = array() ) {
		return add_query_arg(
			array_merge( array( 'page' => self::PAGE_LIST ), $args ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Düzenleme ekranının URL'ini üretir.
	 *
	 * @param int                       $license_id Lisans ID (0 = yeni kayıt).
	 * @param array<string, string|int> $args       Ek query argümanları.
	 * @return string URL.
	 */
	public static function get_edit_url( $license_id = 0, $args = array() ) {
		$query = array( 'page' => self::PAGE_EDIT );

		if ( $license_id > 0 ) {
			$query['id'] = (int) $license_id;
		}

		return add_query_arg( array_merge( $query, $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Endpoint testinin nonce action adı.
	 */
	const NONCE_SELFTEST = 'qmls_selftest';

	/**
	 * Kendi endpoint'ine gerçek bir istek atarak dışarıdan erişilebilirliğini
	 * ölçer.
	 *
	 * Var olmayan bir key gönderilir; sağlıklı bir kurulumda sunucu
	 * HTTP 404 + {"status":"invalid"} döndürmelidir.
	 *
	 * @return array{ok:bool,message:string,detail:string}|null Test istenmediyse null.
	 */
	private function maybe_run_selftest() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['qmls_selftest'] ) ) {
			return null;
		}

		check_admin_referer( self::NONCE_SELFTEST );

		$response = wp_remote_post(
			QMLS_REST_API::get_endpoint_url(),
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'api_key' => 'qmls-selftest-' . wp_generate_password( 12, false ),
						'domain'  => QMLS_Helpers::normalize_domain( home_url() ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Endpoint\'e bağlanılamadı.', 'qmls' ),
				'detail'  => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = trim( (string) wp_remote_retrieve_body( $response ) );
		$data = json_decode( $body, true );

		if ( 404 === $code && isset( $data['status'] ) && 'invalid' === $data['status'] ) {
			return array(
				'ok'      => true,
				'message' => __( 'Endpoint çalışıyor: sunucu beklenen cevabı döndürdü.', 'qmls' ),
				'detail'  => 'HTTP ' . $code . ' — ' . $body,
			);
		}

		return array(
			'ok'      => false,
			'message' => __( 'Endpoint beklenmeyen bir cevap döndürdü.', 'qmls' ),
			'detail'  => 'HTTP ' . $code . ' — ' . mb_substr( $body, 0, 300 ),
		);
	}

	/**
	 * REST endpoint'inin durumunu gösteren teşhis kartını basar.
	 *
	 * Müşteri plugin'i sunucuya ulaşamadığında ilk bakılacak yer burasıdır:
	 * endpoint adresi, route'un gerçekten kayıtlı olup olmadığı ve canlı bir
	 * bağlantı testi tek ekranda görünür.
	 *
	 * @param array{ok:bool,message:string,detail:string}|null $selftest Test sonucu.
	 * @return void
	 */
	private function render_rest_status_card( $selftest ) {
		$endpoint   = QMLS_REST_API::get_endpoint_url();
		$registered = QMLS_REST_API::is_route_registered();
		$plain      = ( false !== strpos( $endpoint, 'rest_route=' ) );
		$test_url   = wp_nonce_url( self::get_list_url( array( 'qmls_selftest' => 1 ) ), self::NONCE_SELFTEST );
		?>
		<div class="qmls-status-card">
			<h2 class="qmls-status-title"><?php esc_html_e( 'Lisans API durumu', 'qmls' ); ?></h2>

			<p class="qmls-status-row">
				<span class="qmls-status-label"><?php esc_html_e( 'Endpoint', 'qmls' ); ?></span>
				<code class="qmls-status-value"><?php echo esc_html( $endpoint ); ?></code>
			</p>

			<p class="qmls-status-row">
				<span class="qmls-status-label"><?php esc_html_e( 'Route kaydı', 'qmls' ); ?></span>
				<span class="qmls-badge <?php echo $registered ? 'qmls-badge-active' : 'qmls-badge-inactive'; ?>">
					<?php
					echo $registered
						? esc_html__( 'Kayıtlı', 'qmls' )
						: esc_html__( 'Kayıtlı değil', 'qmls' );
					?>
				</span>
			</p>

			<p class="qmls-status-row">
				<span class="qmls-status-label"><?php esc_html_e( 'Plugin sürümü', 'qmls' ); ?></span>
				<span class="qmls-status-value"><?php echo esc_html( QMLS_VERSION ); ?></span>
			</p>

			<?php if ( $plain ) : ?>
				<p class="qmls-help">
					<?php esc_html_e( 'Kalıcı bağlantılar "sade" ayarda: bu sitede /wp-json/ adresi çalışmaz, endpoint yalnızca yukarıdaki ?rest_route= biçimiyle erişilebilir. Ayarlar → Kalıcı Bağlantılar ekranından "Yazı adı" gibi bir seçenek kaydedilmesi önerilir.', 'qmls' ); ?>
				</p>
			<?php endif; ?>

			<?php if ( is_array( $selftest ) ) : ?>
				<div class="notice <?php echo $selftest['ok'] ? 'notice-success' : 'notice-error'; ?> qmls-notice">
					<p><strong><?php echo esc_html( $selftest['message'] ); ?></strong></p>
					<p><code><?php echo esc_html( $selftest['detail'] ); ?></code></p>
				</div>
			<?php endif; ?>

			<a class="button qmls-button-block" href="<?php echo esc_url( $test_url ); ?>">
				<?php esc_html_e( 'Bağlantıyı test et', 'qmls' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Lisans listesini kart görünümüyle basar.
	 *
	 * @return void
	 */
	public function render_list_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qmls' ) );
		}

		$licenses = get_posts(
			array(
				'post_type'        => QMLS_CPT::POST_TYPE,
				'post_status'      => array( 'publish', 'draft' ),
				'posts_per_page'   => 200,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		$total_modules = count( QMLS_Helpers::get_module_slugs() );
		$notice        = isset( $_GET['qmls_notice'] ) ? sanitize_key( wp_unslash( $_GET['qmls_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Sadece bilgilendirme mesajı.
		$selftest      = $this->maybe_run_selftest();
		?>
		<div class="wrap qmls-wrap">
			<h1 class="qmls-page-title"><?php esc_html_e( 'QR Lisanslar', 'qmls' ); ?></h1>

			<?php if ( 'deleted' === $notice ) : ?>
				<div class="notice notice-success qmls-notice"><p><?php esc_html_e( 'Lisans çöpe taşındı.', 'qmls' ); ?></p></div>
			<?php endif; ?>

			<?php $this->render_rest_status_card( $selftest ); ?>

			<a class="button button-primary qmls-button-block" href="<?php echo esc_url( self::get_edit_url() ); ?>">
				<?php esc_html_e( '+ Yeni Lisans Ekle', 'qmls' ); ?>
			</a>

			<?php if ( empty( $licenses ) ) : ?>
				<div class="qmls-empty">
					<p><?php esc_html_e( 'Henüz lisans kaydı yok.', 'qmls' ); ?></p>
				</div>
			<?php else : ?>
				<div class="qmls-card-list">
					<?php
					foreach ( $licenses as $license ) :
						$status  = get_post_meta( $license->ID, QMLS_CPT::META_STATUS, true );
						$status  = QMLS_CPT::sanitize_status( $status );
						$domain  = (string) get_post_meta( $license->ID, QMLS_CPT::META_DOMAIN, true );
						$modules = QMLS_Helpers::get_license_modules( $license->ID );
						$is_on   = QMLS_CPT::STATUS_ACTIVE === $status;
						$expired = QMLS_Helpers::is_expired( $license->ID );
						?>
						<a class="qmls-card" href="<?php echo esc_url( self::get_edit_url( $license->ID ) ); ?>">
							<span class="qmls-card-title"><?php echo esc_html( get_the_title( $license ) ); ?></span>
							<?php if ( $expired ) : ?>
								<span class="qmls-badge qmls-badge-inactive"><?php esc_html_e( 'Süresi doldu', 'qmls' ); ?></span>
							<?php else : ?>
								<span class="qmls-badge <?php echo $is_on ? 'qmls-badge-active' : 'qmls-badge-inactive'; ?>">
									<?php echo $is_on ? esc_html__( 'Aktif', 'qmls' ) : esc_html__( 'Pasif', 'qmls' ); ?>
								</span>
							<?php endif; ?>
							<span class="qmls-card-meta">
								<?php if ( '' !== $domain ) : ?>
									<span class="qmls-card-domain"><?php echo esc_html( $domain ); ?></span>
								<?php else : ?>
									<span class="qmls-card-domain qmls-muted"><?php esc_html_e( 'Henüz aktifleşmedi', 'qmls' ); ?></span>
								<?php endif; ?>
								<span class="qmls-card-modules">
									<?php
									printf(
										/* translators: 1: seçili modül sayısı, 2: toplam modül sayısı */
										esc_html__( '%1$d/%2$d modül aktif', 'qmls' ),
										count( $modules ),
										(int) $total_modules
									);
									?>
								</span>
							</span>
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Ekleme/düzenleme formunu basar.
	 *
	 * @return void
	 */
	public function render_edit_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu sayfayı görüntüleme yetkiniz yok.', 'qmls' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Sadece görüntülenecek kaydı ve bildirimi belirliyoruz.
		$license_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
		$notice     = isset( $_GET['qmls_notice'] ) ? sanitize_key( wp_unslash( $_GET['qmls_notice'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$license = $license_id > 0 ? get_post( $license_id ) : null;

		if ( $license_id > 0 && ( ! $license instanceof WP_Post || QMLS_CPT::POST_TYPE !== $license->post_type ) ) {
			wp_die( esc_html__( 'Lisans bulunamadı.', 'qmls' ) );
		}

		$title    = $license ? $license->post_title : '';
		$api_key  = $license ? (string) get_post_meta( $license->ID, QMLS_CPT::META_API_KEY, true ) : '';
		$domain   = $license ? (string) get_post_meta( $license->ID, QMLS_CPT::META_DOMAIN, true ) : '';
		$status   = $license ? QMLS_CPT::sanitize_status( get_post_meta( $license->ID, QMLS_CPT::META_STATUS, true ) ) : QMLS_CPT::STATUS_ACTIVE;
		$notes    = $license ? (string) get_post_meta( $license->ID, QMLS_CPT::META_NOTES, true ) : '';
		$expires  = $license ? (string) get_post_meta( $license->ID, QMLS_CPT::META_EXPIRES, true ) : '';
		$selected = $license ? QMLS_Helpers::get_license_modules( $license->ID ) : array();
		$modules  = QMLS_Helpers::get_modules();
		?>
		<div class="wrap qmls-wrap qmls-form-wrap">
			<h1 class="qmls-page-title">
				<?php echo $license ? esc_html__( 'Lisansı Düzenle', 'qmls' ) : esc_html__( 'Yeni Lisans Ekle', 'qmls' ); ?>
			</h1>

			<a class="qmls-back-link" href="<?php echo esc_url( self::get_list_url() ); ?>">
				<?php esc_html_e( '← Tüm lisanslar', 'qmls' ); ?>
			</a>

			<?php if ( 'saved' === $notice ) : ?>
				<div class="notice notice-success qmls-notice"><p><?php esc_html_e( 'Lisans kaydedildi.', 'qmls' ); ?></p></div>
			<?php elseif ( 'unlocked' === $notice ) : ?>
				<div class="notice notice-success qmls-notice"><p><?php esc_html_e( 'Domain kilidi kaldırıldı. Bir sonraki doğrulamada yeni domain kaydedilecek.', 'qmls' ); ?></p></div>
			<?php elseif ( 'missing_title' === $notice ) : ?>
				<div class="notice notice-error qmls-notice"><p><?php esc_html_e( 'Firma adı zorunludur.', 'qmls' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $license && '' !== $api_key ) : ?>
				<div class="qmls-keybox">
					<span class="qmls-keybox-label"><?php esc_html_e( 'API Key', 'qmls' ); ?></span>
					<code class="qmls-keybox-value" id="qmls-api-key"><?php echo esc_html( $api_key ); ?></code>
					<button type="button" class="button button-secondary qmls-copy-button" data-target="qmls-api-key">
						<?php esc_html_e( 'Kopyala', 'qmls' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qmls-form">
				<input type="hidden" name="action" value="qmls_save_license" />
				<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>" />
				<?php wp_nonce_field( 'qmls_save_license_' . $license_id, 'qmls_nonce' ); ?>

				<div class="qmls-field">
					<label class="qmls-label" for="qmls-title"><?php esc_html_e( 'Firma Adı', 'qmls' ); ?></label>
					<input
						type="text"
						id="qmls-title"
						name="firma_adi"
						class="qmls-input"
						value="<?php echo esc_attr( $title ); ?>"
						placeholder="<?php esc_attr_e( 'Örn. X Restoran', 'qmls' ); ?>"
						required
					/>
				</div>

				<div class="qmls-field">
					<span class="qmls-label"><?php esc_html_e( 'Paket şablonu', 'qmls' ); ?></span>
					<div class="qmls-tier-list">
						<?php foreach ( QMLS_Helpers::get_tiers() as $tier ) : ?>
							<button
								type="button"
								class="button qmls-tier-button"
								data-tier-modules="<?php echo esc_attr( wp_json_encode( array_values( $tier['modules'] ) ) ); ?>"
							>
								<?php echo esc_html( $tier['label'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>
					<p class="qmls-help"><?php esc_html_e( 'Bir pakete tıklamak aşağıdaki modül listesini doldurur; kaydetmeden önce dilediğiniz gibi değiştirebilirsiniz.', 'qmls' ); ?></p>
				</div>

				<div class="qmls-field">
					<span class="qmls-label"><?php esc_html_e( 'Modüller', 'qmls' ); ?></span>
					<div class="qmls-module-list">
						<?php foreach ( $modules as $slug => $label ) : ?>
							<label class="qmls-module-item">
								<input
									type="checkbox"
									name="allowed_modules[]"
									value="<?php echo esc_attr( $slug ); ?>"
									<?php checked( in_array( $slug, $selected, true ) ); ?>
								/>
								<span class="qmls-module-label">
									<span class="qmls-module-name"><?php echo esc_html( $label ); ?></span>
									<span class="qmls-module-slug"><?php echo esc_html( $slug ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="qmls-field">
					<span class="qmls-label"><?php esc_html_e( 'Durum', 'qmls' ); ?></span>
					<label class="qmls-switch-row">
						<input
							type="checkbox"
							name="durum"
							value="<?php echo esc_attr( QMLS_CPT::STATUS_ACTIVE ); ?>"
							class="qmls-switch-input"
							<?php checked( QMLS_CPT::STATUS_ACTIVE, $status ); ?>
						/>
						<span class="qmls-switch" aria-hidden="true"></span>
						<span class="qmls-switch-text"><?php esc_html_e( 'Lisans aktif', 'qmls' ); ?></span>
					</label>
				</div>

				<div class="qmls-field">
					<label class="qmls-label" for="qmls-expires"><?php esc_html_e( 'Son kullanma tarihi (opsiyonel)', 'qmls' ); ?></label>
					<input
						type="date"
						id="qmls-expires"
						name="expires_at"
						class="qmls-input"
						value="<?php echo esc_attr( $expires ); ?>"
					/>
					<p class="qmls-help"><?php esc_html_e( 'Boş bırakılırsa lisansın süresi dolmaz.', 'qmls' ); ?></p>
				</div>

				<div class="qmls-field">
					<span class="qmls-label"><?php esc_html_e( 'Domain', 'qmls' ); ?></span>
					<p class="qmls-domain-value">
						<?php if ( '' !== $domain ) : ?>
							<?php
							printf(
								/* translators: %s: kayıtlı domain */
								esc_html__( 'Kayıtlı domain: %s', 'qmls' ),
								'<strong>' . esc_html( $domain ) . '</strong>'
							);
							?>
						<?php else : ?>
							<?php esc_html_e( 'Henüz aktifleşmedi', 'qmls' ); ?>
						<?php endif; ?>
					</p>
				</div>

				<div class="qmls-field">
					<label class="qmls-label" for="qmls-notes"><?php esc_html_e( 'Notlar (opsiyonel)', 'qmls' ); ?></label>
					<textarea id="qmls-notes" name="notlar" class="qmls-textarea" rows="4"><?php echo esc_textarea( $notes ); ?></textarea>
					<p class="qmls-help"><?php esc_html_e( 'Sadece sizin takibiniz için; API cevabına dahil edilmez.', 'qmls' ); ?></p>
				</div>

				<div class="qmls-sticky-bar">
					<button type="submit" class="button button-primary qmls-button-block qmls-save-button">
						<?php esc_html_e( 'Kaydet', 'qmls' ); ?>
					</button>
				</div>
			</form>

			<?php if ( $license ) : ?>
				<div class="qmls-secondary-actions">
					<?php if ( '' !== $domain ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qmls-inline-form">
							<input type="hidden" name="action" value="qmls_unlock_domain" />
							<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>" />
							<?php wp_nonce_field( 'qmls_unlock_domain_' . $license_id, 'qmls_nonce' ); ?>
							<button type="submit" class="button qmls-button-block qmls-confirm" data-confirm="unlock">
								<?php esc_html_e( 'Domain kilidini kaldır', 'qmls' ); ?>
							</button>
						</form>
					<?php endif; ?>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="qmls-inline-form">
						<input type="hidden" name="action" value="qmls_delete_license" />
						<input type="hidden" name="license_id" value="<?php echo esc_attr( (string) $license_id ); ?>" />
						<?php wp_nonce_field( 'qmls_delete_license_' . $license_id, 'qmls_nonce' ); ?>
						<button type="submit" class="button qmls-button-block qmls-delete-button qmls-confirm" data-confirm="delete">
							<?php esc_html_e( 'Lisansı sil', 'qmls' ); ?>
						</button>
					</form>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Lisans kaydetme isteğini işler.
	 *
	 * @return void
	 */
	public function handle_save() {
		$license_id = isset( $_POST['license_id'] ) ? absint( wp_unslash( $_POST['license_id'] ) ) : 0;

		$this->verify_request( 'qmls_save_license_' . $license_id );

		$title = isset( $_POST['firma_adi'] ) ? sanitize_text_field( wp_unslash( $_POST['firma_adi'] ) ) : '';

		if ( '' === $title ) {
			wp_safe_redirect( self::get_edit_url( $license_id, array( 'qmls_notice' => 'missing_title' ) ) );
			exit;
		}

		$modules = isset( $_POST['allowed_modules'] ) ? (array) wp_unslash( $_POST['allowed_modules'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_modules içinde temizleniyor.
		$modules = QMLS_Helpers::sanitize_modules( $modules );
		$status  = isset( $_POST['durum'] ) ? QMLS_CPT::STATUS_ACTIVE : QMLS_CPT::STATUS_INACTIVE;
		$notes   = isset( $_POST['notlar'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notlar'] ) ) : '';
		$expires = isset( $_POST['expires_at'] ) ? QMLS_Helpers::sanitize_expiry_date( wp_unslash( $_POST['expires_at'] ) ) : '';

		if ( $license_id > 0 ) {
			$license = get_post( $license_id );

			if ( ! $license instanceof WP_Post || QMLS_CPT::POST_TYPE !== $license->post_type ) {
				wp_die( esc_html__( 'Lisans bulunamadı.', 'qmls' ) );
			}

			wp_update_post(
				array(
					'ID'         => $license_id,
					'post_title' => $title,
				)
			);
		} else {
			$license_id = wp_insert_post(
				array(
					'post_type'   => QMLS_CPT::POST_TYPE,
					'post_title'  => $title,
					'post_status' => 'publish',
				),
				true
			);

			if ( is_wp_error( $license_id ) ) {
				wp_die( esc_html( $license_id->get_error_message() ) );
			}

			update_post_meta( $license_id, QMLS_CPT::META_API_KEY, qmls_generate_api_key() );
			update_post_meta( $license_id, QMLS_CPT::META_DOMAIN, '' );
		}

		update_post_meta( $license_id, QMLS_CPT::META_MODULES, $modules );
		update_post_meta( $license_id, QMLS_CPT::META_STATUS, $status );
		update_post_meta( $license_id, QMLS_CPT::META_NOTES, $notes );
		update_post_meta( $license_id, QMLS_CPT::META_EXPIRES, $expires );

		wp_safe_redirect( self::get_edit_url( $license_id, array( 'qmls_notice' => 'saved' ) ) );
		exit;
	}

	/**
	 * Domain kilidini kaldırır (domain meta'sını boşaltır).
	 *
	 * @return void
	 */
	public function handle_unlock_domain() {
		$license_id = isset( $_POST['license_id'] ) ? absint( wp_unslash( $_POST['license_id'] ) ) : 0;

		$this->verify_request( 'qmls_unlock_domain_' . $license_id );

		$license = get_post( $license_id );

		if ( ! $license instanceof WP_Post || QMLS_CPT::POST_TYPE !== $license->post_type ) {
			wp_die( esc_html__( 'Lisans bulunamadı.', 'qmls' ) );
		}

		update_post_meta( $license_id, QMLS_CPT::META_DOMAIN, '' );

		wp_safe_redirect( self::get_edit_url( $license_id, array( 'qmls_notice' => 'unlocked' ) ) );
		exit;
	}

	/**
	 * Lisansı çöpe taşır.
	 *
	 * @return void
	 */
	public function handle_delete() {
		$license_id = isset( $_POST['license_id'] ) ? absint( wp_unslash( $_POST['license_id'] ) ) : 0;

		$this->verify_request( 'qmls_delete_license_' . $license_id );

		$license = get_post( $license_id );

		if ( ! $license instanceof WP_Post || QMLS_CPT::POST_TYPE !== $license->post_type ) {
			wp_die( esc_html__( 'Lisans bulunamadı.', 'qmls' ) );
		}

		wp_trash_post( $license_id );

		wp_safe_redirect( self::get_list_url( array( 'qmls_notice' => 'deleted' ) ) );
		exit;
	}

	/**
	 * Yetki ve nonce kontrolü yapar.
	 *
	 * @param string $action Nonce action adı.
	 * @return void
	 */
	private function verify_request( $action ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'qmls' ) );
		}

		check_admin_referer( $action, 'qmls_nonce' );
	}
}
