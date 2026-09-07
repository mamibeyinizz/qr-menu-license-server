<?php
/**
 * QMLS Teşhis Aracı — QR Menu License Server'dan bağımsız tanı betiği.
 *
 * ÖNEMLİ: Bu dosya kasıtlı olarak bir WordPress "Plugin Name" başlığı
 * TAŞIMAZ. Ana plugin ile aynı pakette (tools/ altında) böyle bir başlık
 * taşırsa, WordPress'in "yükle → hemen etkinleştir" akışı hangi dosyayı
 * etkinleştireceğini dosya sistemi sıralamasına göre YANLIŞ tahmin
 * edebiliyor (bazı sunucularda "Eklentinin geçerli bir üst bilgi bölümü
 * yok" hatasıyla sonuçlanır); listeden elle etkinleştirmek her zaman
 * çalışır çünkü o zaman tek aday dosya kalır. Bu dosyanın kendi başına bir
 * mu-plugin olarak çalışması için "Plugin Name" satırı KULLANICI TARAFINDAN
 * mu-plugins'e kopyalandıktan sonra elle eklenmelidir; ana plugin paketinin
 * içinde asla bulunmamalıdır.
 *
 * KULLANIM
 * --------
 * Bu dosyayı sunucuda şu klasöre kopyalayın ve en üste
 * "Plugin Name: QMLS Teşhis Aracı" satırını kendiniz ekleyin:
 *
 *     wp-content/mu-plugins/qmls-diagnostic.php
 *
 * (mu-plugins klasörü yoksa oluşturun.) Ardından WordPress panelinde
 * Araçlar → QMLS Teşhis ekranını açın.
 *
 * mu-plugins içindeki dosyalar her zaman yüklenir; bu yüzden bu rapor,
 * QR Menu License Server plugin'i hiç yüklenmese bile çalışır ve "plugin
 * gerçekten aktif mi, dosyalar yerinde mi, route kayıtlı mı" sorularını
 * kesin olarak yanıtlar.
 *
 * Teşhis bittikten sonra dosyayı silmeniz yeterlidir; hiçbir ayarı
 * değiştirmez, yalnızca okur.
 *
 * @package QMLS
 */

defined( 'ABSPATH' ) || exit;

/**
 * Teşhis ekranını Araçlar menüsüne ekler.
 *
 * @return void
 */
function qmls_diagnostic_menu() {
	add_management_page(
		'QMLS Teşhis',
		'QMLS Teşhis',
		'manage_options',
		'qmls-diagnostic',
		'qmls_diagnostic_render'
	);
}
add_action( 'admin_menu', 'qmls_diagnostic_menu' );

/**
 * Tek bir rapor satırı basar.
 *
 * @param string      $label  Satır etiketi.
 * @param bool|null   $ok     Durum: true/false, bilgi satırı için null.
 * @param string      $value  Gösterilecek değer.
 * @return void
 */
function qmls_diagnostic_row( $label, $ok, $value = '' ) {
	$mark = '';

	if ( true === $ok ) {
		$mark = '<span style="color:#00733f;font-weight:700">&#10003;</span> ';
	} elseif ( false === $ok ) {
		$mark = '<span style="color:#b32d2e;font-weight:700">&#10007;</span> ';
	}

	printf(
		'<tr><th scope="row" style="width:230px;text-align:left">%s</th><td>%s%s</td></tr>',
		esc_html( $label ),
		$mark, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sabit işaret HTML'i.
		esc_html( $value )
	);
}

/**
 * Plugin'in beklenen dosya listesi.
 *
 * @return string[] Plugin klasörüne göreli yollar.
 */
function qmls_diagnostic_expected_files() {
	return array(
		'qr-menu-license-server.php',
		'includes/class-helpers.php',
		'includes/class-cpt.php',
		'includes/class-admin.php',
		'includes/class-rest-api.php',
	);
}

/**
 * Bir hook'a bağlı callback isimlerini okunabilir biçimde döndürür.
 *
 * @param string $hook Hook adı.
 * @return string Virgülle ayrılmış callback isimleri.
 */
function qmls_diagnostic_hook_callbacks( $hook ) {
	global $wp_filter;

	if ( empty( $wp_filter[ $hook ] ) ) {
		return 'bağlı kod yok';
	}

	$names = array();

	foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			$function = isset( $callback['function'] ) ? $callback['function'] : null;

			if ( is_string( $function ) ) {
				$names[] = $function;
			} elseif ( is_array( $function ) && 2 === count( $function ) ) {
				$class = is_object( $function[0] ) ? get_class( $function[0] ) : (string) $function[0];
				$names[] = $class . '::' . $function[1];
			} elseif ( $function instanceof Closure ) {
				$names[] = 'closure (öncelik ' . $priority . ')';
			} else {
				$names[] = 'bilinmeyen';
			}
		}
	}

	return implode( ', ', $names );
}

/**
 * Teşhis raporunu basar.
 *
 * @return void
 */
function qmls_diagnostic_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Yetkiniz yok.' );
	}

	$plugin_dir  = WP_PLUGIN_DIR . '/qr-menu-license-server';
	$plugin_file = 'qr-menu-license-server/qr-menu-license-server.php';
	$active      = (array) get_option( 'active_plugins', array() );
	$network     = (array) get_site_option( 'active_sitewide_plugins', array() );

	echo '<div class="wrap"><h1>QMLS Teşhis</h1>';

	// 1. Dosyalar.
	echo '<h2>1. Dosyalar</h2><table class="widefat striped"><tbody>';
	qmls_diagnostic_row( 'Plugin klasörü', is_dir( $plugin_dir ), $plugin_dir );

	foreach ( qmls_diagnostic_expected_files() as $relative ) {
		$path   = $plugin_dir . '/' . $relative;
		$exists = file_exists( $path );
		$detail = $exists
			? sprintf(
				'%d byte · %s · md5 %s',
				filesize( $path ),
				gmdate( 'Y-m-d H:i', (int) filemtime( $path ) ),
				substr( md5_file( $path ), 0, 12 )
			)
			: 'DOSYA YOK';

		qmls_diagnostic_row( $relative, $exists, $detail );
	}
	echo '</tbody></table>';

	// 2. Aktivasyon.
	echo '<h2>2. Aktivasyon</h2><table class="widefat striped"><tbody>';
	qmls_diagnostic_row( 'active_plugins içinde', in_array( $plugin_file, $active, true ), $plugin_file );
	qmls_diagnostic_row( 'Ağ genelinde aktif', isset( $network[ $plugin_file ] ), isset( $network[ $plugin_file ] ) ? 'evet' : 'hayır' );
	qmls_diagnostic_row( 'Aktif eklenti sayısı', null, (string) count( $active ) );
	echo '</tbody></table>';

	echo '<p><strong>Aktif eklentiler:</strong><br />';
	echo esc_html( implode( ', ', $active ) );
	echo '</p>';

	// 3. Kod yüklendi mi?
	echo '<h2>3. Kod yüklendi mi?</h2><table class="widefat striped"><tbody>';
	qmls_diagnostic_row( 'QMLS_VERSION sabiti', defined( 'QMLS_VERSION' ), defined( 'QMLS_VERSION' ) ? QMLS_VERSION : 'tanımsız' );

	foreach ( array( 'QMLS_Helpers', 'QMLS_CPT', 'QMLS_Admin', 'QMLS_REST_API' ) as $class ) {
		qmls_diagnostic_row( $class . ' sınıfı', class_exists( $class ), class_exists( $class ) ? 'yüklü' : 'YÜKLENMEMİŞ' );
	}
	echo '</tbody></table>';

	// 4. REST kaydı.
	echo '<h2>4. REST kaydı</h2><table class="widefat striped"><tbody>';

	$has_hook = (bool) has_action( 'rest_api_init' );
	qmls_diagnostic_row( 'rest_api_init hook\'u', $has_hook, $has_hook ? 'kayıtlı callback var' : 'hiç callback yok' );

	$routes     = function_exists( 'rest_get_server' ) ? rest_get_server()->get_routes( 'qmls/v1' ) : array();
	$registered = isset( $routes['/qmls/v1/validate'] );
	qmls_diagnostic_row( '/qmls/v1/validate route', $registered, $registered ? 'kayıtlı' : 'KAYITLI DEĞİL' );
	qmls_diagnostic_row( 'Endpoint adresi', null, rest_url( 'qmls/v1/validate' ) );
	qmls_diagnostic_row( 'Kalıcı bağlantı yapısı', null, get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : 'sade (/wp-json/ çalışmaz)' );

	/*
	 * REST'e müdahale eden kod bu satırlarda görünür. WordPress çekirdeği de
	 * bazılarına bağlanır (ör. rest_cookie_check_errors), bu yüzden sayı değil
	 * callback isimleri listelenir: tanımadığınız bir isim varsa kaynağı odur.
	 */
	foreach ( array( 'rest_endpoints', 'rest_pre_dispatch', 'rest_authentication_errors' ) as $hook ) {
		qmls_diagnostic_row( $hook, null, qmls_diagnostic_hook_callbacks( $hook ) );
	}
	echo '</tbody></table>';

	// 5. Canlı testler.
	echo '<h2>5. Canlı testler</h2><table class="widefat striped"><tbody>';

	$index = wp_remote_get( rest_url(), array( 'timeout' => 15 ) );

	if ( is_wp_error( $index ) ) {
		qmls_diagnostic_row( 'Genel /wp-json/ indeksi', false, $index->get_error_message() );
	} else {
		$body       = json_decode( (string) wp_remote_retrieve_body( $index ), true );
		$namespaces = ( is_array( $body ) && isset( $body['namespaces'] ) ) ? (array) $body['namespaces'] : array();
		qmls_diagnostic_row( 'İndekste qmls/v1', in_array( 'qmls/v1', $namespaces, true ), implode( ', ', $namespaces ) );
	}

	$test = wp_remote_post(
		rest_url( 'qmls/v1/validate' ),
		array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode(
				array(
					'api_key' => 'qmls-teshis-' . wp_generate_password( 10, false ),
					'domain'  => wp_parse_url( home_url(), PHP_URL_HOST ),
				)
			),
		)
	);

	if ( is_wp_error( $test ) ) {
		qmls_diagnostic_row( 'Endpoint POST testi', false, $test->get_error_message() );
	} else {
		$code = (int) wp_remote_retrieve_response_code( $test );
		$body = trim( (string) wp_remote_retrieve_body( $test ) );
		$data = json_decode( $body, true );

		/*
		 * Route hiç yoksa WordPress de 404 döndürür ("rest_no_route"), bu yüzden
		 * yalnızca HTTP koduna bakmak yetmez: gövdedeki status alanı da
		 * "invalid" olmalı.
		 */
		$ok = ( 404 === $code && is_array( $data ) && isset( $data['status'] ) && 'invalid' === $data['status'] );

		qmls_diagnostic_row( 'Endpoint POST testi', $ok, 'HTTP ' . $code . ' — ' . substr( $body, 0, 200 ) );
	}
	echo '</tbody></table>';
	echo '<p>Sağlıklı kurulumda POST testi <code>HTTP 404 — {"status":"invalid"}</code> döndürür (var olmayan bir key gönderilir).</p>';

	// 6. Ortam ve hata kaydı.
	echo '<h2>6. Ortam</h2><table class="widefat striped"><tbody>';
	qmls_diagnostic_row( 'PHP sürümü', version_compare( PHP_VERSION, '7.4', '>=' ), PHP_VERSION );
	qmls_diagnostic_row( 'WordPress sürümü', null, get_bloginfo( 'version' ) );
	qmls_diagnostic_row( 'home_url', null, home_url() );
	qmls_diagnostic_row( 'site_url', null, site_url() );
	echo '</tbody></table>';

	$log = WP_CONTENT_DIR . '/debug.log';

	echo '<h2>7. Hata kaydı</h2>';

	if ( ! file_exists( $log ) ) {
		echo '<p>wp-content/debug.log yok. Kayıt için wp-config.php dosyasına şunları ekleyin:<br />';
		echo '<code>define( \'WP_DEBUG\', true ); define( \'WP_DEBUG_LOG\', true ); define( \'WP_DEBUG_DISPLAY\', false );</code></p>';
	} else {
		$lines = array_slice( array_filter( (array) file( $log ) ), -400 );
		$hits  = array();

		foreach ( $lines as $line ) {
			if ( preg_match( '/qmls|qr-menu-license-server|Fatal error|Parse error/i', $line ) ) {
				$hits[] = $line;
			}
		}

		$hits = array_slice( $hits, -25 );

		if ( empty( $hits ) ) {
			echo '<p>Son kayıtlarda bu plugin ile ilgili hata bulunamadı.</p>';
		} else {
			echo '<pre style="white-space:pre-wrap;background:#fff;border:1px solid #dcdcde;padding:12px">';
			echo esc_html( implode( '', $hits ) );
			echo '</pre>';
		}
	}

	echo '</div>';
}
