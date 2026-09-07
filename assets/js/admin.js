/**
 * QR Menu License Server — admin etkileşimleri.
 *
 * API key kopyalama ve yıkıcı işlemler için onay diyalogu.
 */
( function () {
	'use strict';

	var strings = window.qmlsAdmin || {};

	/**
	 * Verilen metni panoya kopyalar.
	 *
	 * @param {string} text Kopyalanacak metin.
	 * @return {Promise} Kopyalama sonucu.
	 */
	function copyText( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}

		return Promise.reject( new Error( 'clipboard-unavailable' ) );
	}

	/**
	 * Kopyala butonuna geçici geri bildirim yazar.
	 *
	 * @param {HTMLElement} button Buton elemanı.
	 * @param {string}      label  Gösterilecek metin.
	 * @return {void}
	 */
	function flashLabel( button, label ) {
		var original = strings.copy || button.getAttribute( 'data-original-label' ) || button.textContent;

		button.setAttribute( 'data-original-label', original );
		button.textContent = label;
		button.classList.add( 'qmls-copied' );

		window.setTimeout( function () {
			button.textContent = original;
			button.classList.remove( 'qmls-copied' );
		}, 2000 );
	}

	/**
	 * Kopyala butonlarını bağlar.
	 *
	 * @return {void}
	 */
	function initCopyButtons() {
		var buttons = document.querySelectorAll( '.qmls-copy-button' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				var target = document.getElementById( button.getAttribute( 'data-target' ) );

				if ( ! target ) {
					return;
				}

				var value = ( target.textContent || '' ).trim();

				copyText( value ).then(
					function () {
						flashLabel( button, strings.copied || 'Kopyalandı!' );
					},
					function () {
						window.prompt( strings.copyFailed || 'Kopyalanamadı:', value );
					}
				);
			} );
		} );
	}

	/**
	 * Onay gerektiren butonları bağlar.
	 *
	 * @return {void}
	 */
	function initConfirmButtons() {
		var buttons = document.querySelectorAll( '.qmls-confirm' );
		var messages = {
			delete:     strings.confirmDelete || 'Bu kayıt silinsin mi?',
			regenerate: strings.confirmRegenerate || 'API key yeniden üretilsin mi?',
			unlock:     strings.confirmUnlock || 'Domain kilidi kaldırılsın mı?'
		};

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function ( event ) {
				var type = button.getAttribute( 'data-confirm' );
				var message = messages[ type ] || messages.unlock;

				if ( ! window.confirm( message ) ) {
					event.preventDefault();
				}
			} );
		} );
	}

	/**
	 * Paket şablonu butonlarını bağlar: tıklanınca modül checkbox'larını
	 * pakete göre işaretler/kaldırır, formu göndermez.
	 *
	 * @return {void}
	 */
	function initTierButtons() {
		var buttons = document.querySelectorAll( '.qmls-tier-button' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				var modules = [];

				try {
					modules = JSON.parse( button.getAttribute( 'data-tier-modules' ) || '[]' );
				} catch ( e ) {
					modules = [];
				}

				var checkboxes = document.querySelectorAll( '.qmls-module-list input[type="checkbox"]' );

				Array.prototype.forEach.call( checkboxes, function ( checkbox ) {
					checkbox.checked = -1 !== modules.indexOf( checkbox.value );
				} );
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initCopyButtons();
			initConfirmButtons();
			initTierButtons();
		} );
	} else {
		initCopyButtons();
		initConfirmButtons();
		initTierButtons();
	}
} )();
