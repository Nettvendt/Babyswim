<?php
//namespace Babyswim\knutsp;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kursoversikt_Shortcode {

	public function woo_render( $atts ) {
		ob_start();
		Kursoversikt::woo_render();
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}

	public function tickera_render( $atts ) {
		ob_start();
		Kursoversikt::tickera_render();
		$html = ob_get_contents();
		ob_end_clean();
		return $html;
	}

	public function __construct() {
		add_shortcode( 'kursoversikt', Kursoversikt::$use_tickera ? [ $this, 'tickera_render' ] : [ $this, 'woo_render' ] );
	}
}