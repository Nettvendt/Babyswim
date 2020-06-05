<?php
/**
 *
 */
class Kursoversikt_Preorder {

	const line_height = 18.4;	// px

	public static function is_registered( $order ): bool {
		if ( method_exists( $order, 'get_user_id' ) && method_exists( $order, 'get_billing_email' ) ) {
			$is_registered = $order->get_user_id() > 0 ? true : email_exists( $order->get_billing_email() > 0 );
		} else {
			$is_registered = false;
		}
		return $is_registered;
	}
	
	public static function order_events( array $events, bool $reverse = false ): array {
		$ordered_events = [];
		echo '<pre style="font-weight: bold; color: red;">';
		foreach( $events as $event ) {
			$key = '';
			$terms = get_the_terms( $event, Kursoversikt::$woo_loc_tax );
			if ( ! isset( $terms[0] ) ) {
				echo PHP_EOL, 'Mangler sted: ', $key, ': ', $event->post_title;
			}
			$time = Kursoversikt::get_event_start_time( $event );
			$key .= $reverse ? '' : current_time( 'U' ) - $time > 0 ? 1 : 0;
			$key .= isset( $terms[0] ) && $terms[0]->parent == 0 ? substr( '0' . $terms[0]->order, -2 ) : '-';
			$term_slug = isset( $terms[0] ) && $terms[0]->parent == 0 ? $terms[0]->slug : '-';
			$key .= str_pad( substr( isset( $terms[0] ) && $terms[0]->parent == 0 ? $terms[0]->slug : '-', 0, 2 ), 2, '_' );
			$key .= substr( date( 'ymdHi', $time ), 0, -1 );
			if ( array_key_exists( $key, $ordered_events ) ) {
				echo PHP_EOL, $key, ': ', $event->post_title;
			}
			$ordered_events[ $key ] = $event;
		}
		if ( count( $events ) != count( $ordered_events ) ) {
			echo PHP_EOL, 'DUPLIKAT(ER) FUNNET!';
		}
		echo '</pre>';
		ksort( $ordered_events, SORT_STRING );
		if ( $reverse ) {
			$ordered_events = array_reverse( $ordered_events );
		}
		return count( $ordered_events ) == count( $events ) ? $ordered_events : $events;
	}

	private static function ppp_link_life( int $delay = 0 ): array {
		$nonce_life = apply_filters( 'ppp_nonce_life', 60 * 60 * 48 ); // 48 hours
		$half_nonce = intval( $nonce_life / 2 );
		$limit = ( Webfacing_Public_Post_Preview::nonce_tick( $delay ) * $half_nonce ) + ( 12 * HOUR_IN_SECONDS );
		return [ $limit - $half_nonce, $limit + $half_nonce ];
	}
	
	
	public static function page() {
		if ( is_admin() && current_user_can( 'read_private_pages' ) ) {
			$title   = get_admin_page_title();
			$is_post = $_SERVER['REQUEST_METHOD'] == 'POST';
			$selected_age = $is_post && isset( $_POST['age'] ) ? intval( $_POST['age'] ) : false;
			$selected_events = $is_post && isset( $_POST['events'] ) && is_array( $_POST['events'] ) && count( $_POST['events'] ) ? array_map( 'intval', $_POST['events'] ) : false;
			if ( is_array( $selected_events ) && $selected_events[0] <= 0 && count( $selected_events ) > 1 ) {
				unset ( $selected_events[0] );
			}
			$checked_unreg  = $is_post && ! empty( $_POST['unreg'] );

			$selected_customers_emails = $is_post && isset( $_POST['submit'] ) && isset( $_POST['customers'] ) && is_array( $_POST['customers'] ) && count( $_POST['customers'] ) ? array_map( 'sanitize_email', $_POST['customers'] ) : false;
			$selected_coupon_id = $is_post && isset( $_POST['submit'] ) && isset( $_POST['coupon'] ) ? intval( $_POST['coupon'] ) : false;

			$start = Kursoversikt::get_events_from();
			$terms = [ $selected_age ? $selected_age : Kursoversikt::$woo_event_cat[ Kursoversikt::$woo_event_tax ] ];
			$tax_query = [
				[ 'taxonomy' => Kursoversikt::$woo_event_tax, 'field' => 'term_id', 'terms' => $terms ],
			];
			$events = get_posts( [
				'post_type'       => 'product',
				'posts_per_page'  => -1,
				'meta_key'        => Kursoversikt::pf . 'start',
				'meta_value'      => date_i18n( 'Y-m-d', $start ),
				'meta_compare'    => '>',
				'meta_type'       => 'DATE',
				'tax_query'       => $tax_query,
//				'orderby'         => [ 'post_title' => 'ASC', 'meta_key' => 'ASC' ]
			] );
			$events = self::order_events( $events );

			$customer_data = [];
			if ( $selected_events && ( ! isset( $selected_events[0] ) || ( isset( $selected_events[0] ) && $selected_events[0] > 0 ) ) ) {
				foreach ( $selected_events as $selected_event ) {
					$order_ids = Kursoversikt_Deltakere::get_order_ids_by_product( $selected_event, [ 'completed' ] );
//					$order_ids = Kursoversikt_Deltakere::get_order_ids_by_product( $selected_event ); // corona-temp
					foreach ( $order_ids as $order_id ) {
						$order = wc_get_order( $order_id );
						if ( method_exists( $order, 'get_billing_email' ) && ! ( $checked_unreg && self::is_registered( $order ) ) ) {
							$customer_email = sanitize_email( $order->get_billing_email() );
							$customer_name  = esc_attr( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
							$customer_phone = esc_attr( $order->get_billing_phone() );
							if ( $customer_email ) {
								$customer_data[ $customer_email ] = [ 'name' => $customer_name, 'phone' => $customer_phone, 'count' => true ];
							}
						}
					}
				}
				uasort( $customer_data, function( $a, $b ) {
					return $a['name'] <=> $b['name'];
				} );
			} else {
				$roles = [ 'instructor' ];
				$roles = $selected_events[0] == -1 ? $roles : $roles[] = 'customer';
				$customers = get_users( [ 'role__in' => $roles, 'orderby' => 'display_name', 'count_total' => false ] );
				foreach ( $customers as $customer ) {
					if ( ! ( $customer->spam || $customer->deleted ) )// {
						$count = wc_get_customer_order_count( $customer->ID );
						if ( intval( $customer->user_status ) == 0 && (
							time() - intval( get_user_meta( $customer->ID, 'last_update',    true ) ) < 1 * MONTH_IN_SECONDS ||
							time() - intval( get_user_meta( $customer->ID, 'wp-last-login',  true ) ) < 2 * MONTH_IN_SECONDS ||
							time() - intval( get_user_meta( $customer->ID, 'wc_last_active', true ) ) < 3 * MONTH_IN_SECONDS ||
							$count
						) ) {
							$name  = $customer->display_name != $customer->user_login ? $customer->display_name : $customer->first_name . ' ' . $customer->last_name;
							$name  = trim( $name ) ? $name : get_user_meta( $customer->ID, 'nickname', true );
							$customer_data[ $customer->user_email ] = [ 'name' => $name, 'phone' => get_user_meta( $customer->ID, 'billing_phone', true ), 'count' => $count ];
						}
				}
			}

			$pages    = get_posts( [
				'post_type' => 'page',
				'post_status' => [ /*'draft', */'pending', 'future' ],
				'posts_per_page' => -1,
			] );
			$products = get_posts( [
				'post_type' => 'product',
				'post_status' => [ /*'draft', */'pending', 'future' ],
//				'meta_key' => Kursoversikt::pf . 'start',
				'posts_per_page' => -1,
//				'orderby' => [ 'post_title' => 'ASC', 'menu_order' => 'ASC', 'meta_key' => 'ASC' ],
			] );
			$products = self::order_events( $products );
			$export_text = 'Vis e-postadr./lenker';
			$email_text  = 'Send e-post';
			$sms_text    = 'Send SMS';
			$debug = ! empty( $_POST['debug'] );
			$current_user = wp_get_current_user();
?>
	<style>
		label { font-weight: bold; }
		p { margin-bottom: 0; }
	</style>
	<div class="wrap">
		<h1><?=$title?></h1>
		<h2>Velg kunder og enten upublisert kursoversiktside eller enkeltvise upubliserte kurs å sende kunder en &laquo;magisk&raquo; lenke til</h2>
		<!--p>Lenkene du sender varer kun inntil <?=Kursoversikt::$preview_life?> dager <span class="description">(<a href="options-general.php?page=<?=Kursoversikt::$pf?>page#preview-life">Innnstillinger for offentlig forhåndsvisning</a>).<br /></p-->
		
		<form action="<?=admin_url(get_current_screen()->parent_file.'?page='.esc_attr($_GET['page']))?><?=$selected_events?'#customers':''?>" method="post">
			<p>
				<label for="age">Aldersgruppe:</label>
				<select id="age" name="age">
					<option<?=selected($selected_age,false,false)?>>--alle aldersgrupper + reg.kunder --</option>
<?php
			$cats = get_terms( [
				'taxonomy'   => Kursoversikt::$woo_event_tax,
				'parent'     => Kursoversikt::$woo_event_cat[ Kursoversikt::$woo_event_tax ],
				'hide_empty' => true,
			] );
			$ages = [];
			foreach( $cats as $cat ) {
				$key = intval( explode( '-', $cat->slug )[0] );
				$ages[ $key ] = $cat;
			}
			ksort( $ages, SORT_NUMERIC );
			foreach( $ages as $age ) {
				echo PHP_EOL, '<option value="', $age->term_id, '"', selected( intval( $age->term_id ) == $selected_age, true, false ), '>', $age->name, '</option>';
			}
?>
				</select>
				<button type="submit" style="vertical-align: bottom;">Filtrer tidligere kurs</button>
			</p>
			<p>
				<label for="event" style="vertical-align: top;">Vis kunder som har deltatt på følgende kurs startet etter <?=date_i18n('d.m.Y',$start)?> <small>(ca. ant. påmeld.)</small>:<br /></label>
				<select id="event" name="events[]" multiple="multiple" data-select="false" style="min-height: <?=(min(count($events)+2,20))*self::line_height+2?>px; min-width: 40em;">
					<option<?=selected(empty($selected_events)||$selected_events[0]===0,true,false)?>>-- kun registrerte kunder og instruktører --</option>
					<option<?=selected($selected_events[0]==-1,true,false)?> value="-1">-- kun instruktører --</option>
					<option>-- velg alle kurs --</option>
<?php
			foreach ( $events as $event ) {
				$terms = get_the_terms( $event, Kursoversikt::$woo_event_tax );
				$term = $terms && is_array( $terms ) && count( $terms) == 1 ? $terms[0] : false;
				if ( $term && $term->parent == Kursoversikt::$woo_event_cat[ Kursoversikt::$woo_event_tax ] ) {
					$event_id = intval( $event->ID );
//					$count = count( Kursoversikt_Deltakere::get_order_ids_by_product( $event_id, [ 'completed' ] ) );
					$count = count( Kursoversikt_Deltakere::get_order_ids_by_product( $event_id ) );	// corona-temp
					echo PHP_EOL, '<option value="', $event_id, '"', $selected_events ? selected( in_array( $event_id, is_array( $selected_events ) ? $selected_events : [] ), true, false ) : '', disabled( $count, 0, false ), '>', $event->post_title, ' (', $count, ')</option>';
				}
			}
?>
				</select>
				<input id="unreg" type="checkbox" name="unreg"<?=checked($checked_unreg,true,false)?>style="vertical-align: bottom; position: relative; bottom: 4px;" /> <label for="unreg" style="vertical-align: bottom;">kun uregisterte</label> &nbsp;
				<button type="submit" style="vertical-align: bottom;">Filtrer kunder</button>
				<br clear="left" />(shift eller ctrl klikk for å velge flere eller for å oppheve et valg)<br />
			</p>
		</form>
<?php
			$customers_count = count( $customer_data );
			if ( $customers_count ) {
?>
		<form action="<?=admin_url(get_current_screen()->parent_file.'?page='.esc_attr($_GET['page']))?>#submit" method="post">
			<p style="float: left; padding-right: 1em;">
				<label for="customers" title="Antall: <?=$customers_count?>.">Kunder/instruktører (mottakere)</label><br />
				<select id="customers" multiple="multiple" data-select="false" name="customers[]" style="min-height: <?=(min(count($customer_data)+1,20))*self::line_height?>px; min-width: 14em;">
					<option>-- velg alle --</option>
<?php
				$selected_pages_ids = isset( $_POST['pages'] ) ? array_map( 'intval', $_POST['pages'] ) : false;
				$selected_products_ids  = $is_post && isset( $_POST['submit'] ) && isset( $_POST['products' ] ) && is_array( $_POST['products' ] ) && count( $_POST['products' ] ) ? array_map( 'intval', $_POST['products' ] ) : false;
				foreach ( $customer_data as $customer_email => $customer ) {
					$customer_name  = $customer['name' ];
					$customer_phone = $customer['phone'];
					$order_count    = $customer['count'];
					$style          = $order_count ? '' : ' style="background-color: lightgray;"';
?>
					<option value="<?=$customer_email?>"<?=selected(in_array($customer_email,is_array($selected_customers_emails)?$selected_customers_emails:[]),true,false)?> title="E-post:  <?=$customer_email?>, mobil: <?=$customer_phone?>, ordre: <?=$order_count?>."<?=$style?>><?=$customer_name?></option>
<?php
				}
?>
				</select>
			</p>
			<p style="float: left; padding-right: 1em;">
				<label for="pages" title="Ventende kurs. Antall sider: <?=count($pages)?>.">Oversikt-side<?=count($pages)!=1?'r':''?> (å ta med lenke til)</label><br />
				<select id="pages" name="pages[]" multiple="multiple" style="min-height: <?=count($pages)*self::line_height?>px; min-width: 14em;">
<?php
				if ( count( $products ) ) {
					foreach ( $pages as $page ) {
						$page_id = intval( $page->ID );
						if ( get_page_template_slug( $page_id ) == 'templates/oceanwp-calendar.php' || has_shortcode( $page->post_content, 'kursoversikt' ) || has_block( 'babyswim/kursoversikt', $page_id ) ) {
?>
					<option value="<?=$page->ID?>"<?=selected(in_array($page_id,is_array($selected_pages_ids)?$selected_pages_ids:[]),true,false)?> title="Dato: <?=get_the_date('',$page)?>"><?=get_the_title($page)?></option>
<?php
						}
					}
				}
?>
				</select>
				<br />
				<label for="coupons">Rabattkode å ta med</label><br />
				<select id="coupons" name="coupon" style="min-width: 14em;">
					<option <?=selected($selected_coupon_id,false,false)?>> -- velg rabatt -- </option>
<?php
					$coupons = get_posts( [ 'post_type' => 'shop_coupon', 'posts_per_page' => -1 ] );
					foreach ( $coupons as $coupon ) {
						$coupon_id = intval( $coupon->ID );
						$value     = floatval( get_post_meta( $coupon_id, 'coupon_amount', true ) );
						$unit      = get_post_meta( $coupon_id, 'discount_type', true ) == 'percent' ? '%' : ' kr';
						$value    .= $unit;
?>
					<option value="<?=$coupon_id?>"<?=selected($selected_coupon_id,$coupon_id,false)?> title="<?=get_the_title($coupon_id)?>"><?=mb_substr(esc_html(get_the_excerpt($coupon_id)),0,17)?>: <code><?=$value?></code></option>
<?php
					}
?>
				</select>
<?php
//				}
?>
			</p>
			<p style="float: left;">
				<label for="products" title="Antall: <?=count($products)?>.">Ventende enkeltkurs (å ta med lenke til)</label><br />
				<select id="products" multiple="multiple" name="products[]" style="min-height: <?=(count($products)+1)*self::line_height?>px; min-width: 40em;">
<?php
				foreach ( $products as $product ) {
					$product_id = intval( $product->ID );
					$count = count( Kursoversikt_Deltakere::get_order_ids_by_product( $product_id ) );
					$disab = ! wc_get_product( $product_id )->is_in_stock();
?>
					<option value="<?=$product_id?>"<?=selected(in_array($product_id,is_array($selected_products_ids)?$selected_products_ids:[]),true,false)?><?=disabled($disab)?> title="<?=$disab?'Fullt':'Ledig'?>."><?=get_the_title($product)?> (<?=$count?>)</option>
<?php
				}
?>
				</select>
			</p>
			<p style="clear: left"/>(shift eller ctrl klikk for å velge flere eller for å oppheve et valg) &nbsp; &nbsp; &nbsp; Det er bare <em>upubliserte</em>		<small>(status: <!--kladd, -->ventende eller planlagt)</small> sider og kurs som vises her.</p><p></p>
				<label for="text1">Ekstra tekst over eventuelle lenker:</label><br />
				<textarea id="text1" name="text1" cols="90" rows="2"><?=isset($_POST['text1'])?esc_textarea($_POST['text1']):''?></textarea>
				<br clear="left" />
				<label for="text2">Ekstra tekst under eventuelle lenker:</label><br />
				<textarea id="text2" name="text2" cols="90" rows="1"><?=isset($_POST['text2'])?esc_textarea($_POST['text2']):''?></textarea>
			</p>
			<p>
				<input id="debug" type="checkbox" name="debug"<?=checked(WP_DEBUG,true,false)?> /><label for="debug" style="vertical-align: top;">kun test <small>(én e-post eller én SMS sendes til deg selv, uansett valgt(e) mottaker(e))</small></label>
			</p>
			<p>
<?php
				submit_button( $export_text, 'large', 'submit', false );
				echo ' &nbsp; ';
				submit_button( 'Forhåndsvis melding', 'primary', 'submit', false );
				echo ' &nbsp; ';
				submit_button( $email_text,  'large', 'submit', false );
				if ( function_exists( 'wc_twilio_sms' ) ) {
					echo ' &nbsp; ';
					submit_button( $sms_text, 'large', 'submit', false );
					echo ' &nbsp; ';
					echo PHP_EOL, '<a href="admin.php?page=wc-settings&tab=twilio_sms#wc_twilio_sms_test_mobile_number">Til testsending av SMS-melding til valgt nr</a>.';
				}
 ?>
				<input type="hidden" name="age" value="<?=$selected_age?>" />
<?php
	 			foreach ( is_array( $selected_events ) ? $selected_events : [] as $selected_event ) {
?>
				<input type="hidden" name="events[]" value="<?=$selected_event?>" />
<?php
				}
?>
			</p>
		</form>
		<br />
<?php
				if ( $selected_customers_emails || $_POST['submit'] == $export_text ) {
		 			if ( ! ( isset( $_POST['submit'] ) && $_POST['submit'] == $export_text ) ) {
						echo PHP_EOL, '<h3>Til: ';
						$all_emails = [];
						foreach ( $selected_customers_emails as $customer_email ) {
							if ( $customer_email ) {
								$customer_name  = $customer_data[ $customer_email ]['name'];
								$customer_phone = $customer_data[ $customer_email ]['phone'];
								$all_emails[] = '<a href="mailto:' . ( $debug ? $current_user->user_email : $customer_email ) . '" title="Telefon: ' . $customer_phone . '">' . $customer_name . '</a>'/* . ( empty( $customer_phone ) ? ' &nbsp; <small>(OBS: mobilnummer mangler)</small>' : '')*/;
							}
						}
						echo implode( ', ', $all_emails ), '</h3>';
						$subject = $selected_pages_ids || $selected_products_ids ? 'Tilbud om ' . strtolower( $title ) : 'Melding til kursdeltakere';
?>
		<h4>Emne: [<?=get_option( 'woocommerce_email_from_name', get_bloginfo() )?>] <?=$subject?></h4>
		<div style="border: 1px solid darkgray; width: 60em; padding: 0 1em 1em;">
		<p>Kjære kunde og kursdeltaker</p>
		<p><?=str_replace(PHP_EOL,'<br/>',esc_html($_POST['text1']))?></p>
<?php
						if ( $selected_pages_ids || $selected_products_ids ) {
?>
		<h4>Kurs du nå kan forhåndspåmelde deg til:</h4>
<?php
							if ( $selected_products_ids ) {
?>
		<ul style="list-style-type: disc; list-style-position: inside;">
<?php
								foreach ( $selected_products_ids as $product_id ) {
									echo PHP_EOL, '<li><a href="', Webfacing_Public_Post_Preview::get_preview_link( get_post( $product_id ) ), '">', get_the_title( $product_id ), '</a></li>';
								}
?>
		</ul>
<?php
								$count = $selected_products_ids ? count( $selected_products_ids ) : 0;
?>				
		<!--p><strong>Obs:</strong> Lenken<?=$count==1?'':'e'?> over virker kun til <?//=date_i18n('l d.m.y \k\l 24',strtotime('+'.(Kursoversikt::$preview_life-1).' days',current_time('U')))?>.</p-->
		</ul>
<?php
							}
							if ( $selected_pages_ids ) {
?>
		<ul style="list-style-type: disc; list-style-position: inside;">
<?php
								foreach ( $selected_pages_ids as $page_id ) {
									echo PHP_EOL, '<li><a href="', Webfacing_Public_Post_Preview::get_preview_link( get_post( $page_id ) ), '">', get_the_title( $page_id ), '</a></li>';
								}
?>				
		<p><strong>Obs:</strong> Lenken gir nå en oversikt, men forhåndspåmelding til <?=$count?'de øvrige':'disse'?> kursene starter først <?=date_i18n('l d.m.y \k\l H.i',Kursoversikt::$preview_dt )?>.</p>
<?php
							}
?>
		</ul>
		<p>Velkommen tilbake til kurs hos oss neste sesong!</p>
<?php
						}
						if ( $selected_coupon_id ) {
?>
		<p>Rabattkode: <code><?=esc_attr(get_post($selected_coupon_id)->post_title)?></code></p>
<?php
						}
?>
		<p><?=str_replace(PHP_EOL,'<br/>',esc_html($_POST['text2']))?></p>
		<p>-- <br />Med vennlig hilsen<br />
		<?=$current_user->display_name?><br />
		<?=get_option('woocommerce_email_from_name',get_bloginfo())?>
		</p>
		</div>
<?php
					}
					if ( isset( $_POST['submit'] ) && $_POST['submit'] == $email_text ) {
						self::send_email( strtolower( $title ), $customer_data, $selected_customers_emails ? $selected_customers_emails : [], $selected_pages_ids ? $selected_pages_ids : [], $selected_products_ids ? $selected_products_ids : [], $selected_coupon_id, esc_html( $_POST['text1'] ), esc_html( $_POST['text2'] ), $debug );
					} elseif ( isset( $_POST['submit'] ) && $_POST['submit'] == $export_text ) {
						self::export_email( $customer_data, $selected_customers_emails ? $selected_customers_emails : [], $selected_pages_ids ? $selected_pages_ids : [], $selected_products_ids ? $selected_products_ids : [], $selected_coupon_id );
					} elseif ( isset( $_POST['submit'] ) && $_POST['submit'] == $sms_text ) {
						self::send_sms( strtolower( $title ), $customer_data, $selected_customers_emails ? $selected_customers_emails : [], $selected_pages_ids ? $selected_pages_ids : [], $selected_products_ids ? $selected_products_ids : [], $selected_coupon_id, esc_html( $_POST['text1'] ), esc_html( $_POST['text2'] ), $debug );
					} 
				}
?>
	</div>
 <?php
			} else {
				echo PHP_EOL, '<p>Ingen kunder har deltatt på dette/disse. Velg et annet filter.</p>';
			}
			Kursoversikt::footer();
		}
	}
	
	public static function send_email( string $title, array $customer_data, array $selected_customers_emails, array $selected_pages_ids, array $selected_products_ids, int $selected_coupon_id, string $text1, string $text2, bool $debug ) {
		include_once 'covid.php';
		$current_user = wp_get_current_user();
		$tos = [];
		foreach ( $selected_customers_emails as $customer_email ) {
			if ( $customer_email ) {
				$customer_name = $customer_data[ $customer_email ]['name'];
				$tos[] = $customer_name . ' <' . $customer_email . '>';
			}
		}
		$tos      = $debug ? [ $tos[0] ] : $tos;
		$subject  = '[' . get_bloginfo(). '] ' . ( $selected_pages_ids || $selected_products_ids || $selected_coupon_id ?
			'Tilbud om ' . $title :
			'Viktig melding til kursdeltakere' );
		$message  = '<p>Kjære kunde og kursdeltaker</p>' . PHP_EOL;
		$message .= '<p>' . str_replace( PHP_EOL, '<br />', $text1 ) . '</p>' . PHP_EOL;
		if ( $selected_pages_ids || $selected_products_ids ) {
			$message .= '<h4>Kurs du nå kan forhåndspåmelde deg til:</h4>';
			if ( $selected_products_ids ) {
				$message .= '<ul>' . PHP_EOL;
				foreach ( $selected_products_ids as $product_id ) {
					$message .= '<li><a href="' . Webfacing_Public_Post_Preview::get_preview_link( get_post( $product_id ) ) . '">' . get_the_title( $product_id ) . '</a></li>' . PHP_EOL;
				}
				$message .= '</ul>' . PHP_EOL;
				$count = $selected_products_ids ? count( $selected_products_ids ) : 0;
//				$message .= '<p>Obs: Lenken' . ( $count == 1 ? '' : 'e' ) . ' over virker kun til ' . date_i18n( 'l d.m.y \k\l 24', strtotime( '+' . ( Kursoversikt::$preview_life - 1 ) . ' days', current_time( 'U' ) ) ) . '.</p>';
			}
			if ( $selected_pages_ids ) {
				$message .= '<ul>' . PHP_EOL;
				foreach ( $selected_pages_ids as $page_id ) {
					$message .= '<li><a href="' . Webfacing_Public_Post_Preview::get_preview_link( get_post( $page_id ), 2 ) . '">' . get_the_title( $page_id ) . '</a></li>' . PHP_EOL;
				}
				$message .= '</ul>' . PHP_EOL;
				$message .= '<p><strong>Obs:</strong> Lenken gir nå en oversikt, men forhåndspåmelding til ' . ( count( $selected_products_ids ) ? 'de øvrige' : 'disse' ) . ' kursene starter først ' . date_i18n( 'l d.m.y \k\l H.i', Kursoversikt::$preview_dt ) . '.</p>' . PHP_EOL;
			}
			$message .= '<p>Velkommen tilbake til kurs igjen hos oss neste sesong!</p>' . PHP_EOL;
		}
		if ( $selected_coupon_id ) {
			$message .= '<p>Rabattkode: <code>' . get_post( $selected_coupon_id )->post_title .'</code></p>'. PHP_EOL;
		}
		$message .= '%%';
		$message .= '<p>' . str_replace( PHP_EOL, '<br />', $text2 ) . '</p>' . PHP_EOL;
		$message .= '<p>-- <br />Med vennlig hilsen<br />' . $current_user->display_name . '<br />' . get_option( 'woocommerce_email_from_name', get_bloginfo() ) . '<br />'. PHP_EOL;
		$headers = [ 'Content-type: text/html; charset=UTF-8' ];
		echo PHP_EOL, '<ol>';
		foreach ( $tos as $to ) {
			$orig_to = $to;
			$coupons = get_covid_coupons( $to );
			$to = WP_DEBUG ? 'knutsp+' . get_bloginfo() . '@gmail.com' : ( $debug ? $current_user->user_email : $to );
			$ctxt = '<p>';
			foreach ( $coupons as $code => $coupon ) {
				$ctxt .= 'Rabattkode: <code>' . $code . '</code> &nbsp; &ndash; &nbsp; (for: ' . $coupon . ')<br />';
			}
			$ctxt .= '</p>' . PHP_EOL;
			if ( count( $coupons ) ) {
				$message = str_replace( '%%', $ctxt, $message );
			} else {
				$message = str_replace( '%%', WP_DEBUG ? $orig_to : '', $message );
			}
			$ok = wp_mail( $to, $subject, $message, $headers );
			echo PHP_EOL, '<li><small>E-post til ', htmlentities( $to ), $ok ? '' : '<strong>ikke</strong> ', ' sendt!</small></li>';
			sleep( 1 );
		}
		echo PHP_EOL, '</ol><p>Se <a href="admin.php?page=email-log">e-postloggen</a>.</p>';
	}

	public static function export_email( array $customer_data, array $selected_customers_emails, array $selected_pages_ids, array $selected_products_ids, int $selected_coupon_id ) {
		$all_emails = [];
		foreach ( $selected_customers_emails as $customer_email ) {
			$customer_name = $customer_data[ $customer_email ]['name'];
			$all_emails[] = $customer_name . ' &lt;'.  $customer_email . '&gt;';
		}
		if ( $selected_customers_emails ) {
			echo PHP_EOL, '<p><strong>Valgte mottakere:</strong> Kopier til til/adressefeltet i din egen app:<pre style="border: solid black 1px; padding: .5em; background-color: white; width: 40%;">', implode( ',' . PHP_EOL, $all_emails ), '</pre>';
			echo PHP_EOL, '<form method="post" action="', plugins_url( 'export-mailchimp.php', __FILE__ ), '" style="display: inline">';
			foreach ( $selected_customers_emails as $customer_email ) {
				if ( $customer_email ) {
					$customer_name = $customer_data[ $customer_email ]['name'];
					$names = explode( ' ', $customer_name );
					$last_name   = array_pop( $names );
					$first_name  = count( $names ) > 0 ? $names[0] : '' ;
					$first_name .= count( $names ) > 1 ? ' ' . $names[1] : '';
					echo PHP_EOL, '<input type="hidden" name="mailchimp[]" value="', $customer_email, ',', trim( $first_name ), ',', trim( $last_name ), '" />';
				}
			}
			echo PHP_EOL, '<button type="submit">Last ned CSV-fil for Excel/MailChimp</button></form>';
		}
		echo PHP_EOL, '<p><strong>Forslag til emnefelt:</strong> Kopier til til/emnefeltet i din egen app:<pre style="border: solid black 1px; padding: .5em; background-color: white; width: 40%;">[', get_option( 'woocommerce_email_from_name', get_bloginfo() ), '] Melding til kursdeltakere</pre></p>';
		echo PHP_EOL, '<p><small>skriv så din egen e-postmelding.</small></p>';
		if ( $selected_pages_ids || $selected_products_ids || $selected_coupon_id ) {
			echo PHP_EOL, '<p>Valgte lenker å ta med i meldingen:</p>';
			echo PHP_EOL, '<pre style="border: solid black 1px; padding: .5em; background-color: white; width: 100%;">';
			if ( $selected_pages_ids ) {
				foreach ( $selected_pages_ids as $page_id ) {
					echo Webfacing_Public_Post_Preview::get_preview_link( get_post( $page_id ) ), PHP_EOL;
				}
			}
			if ( $selected_products_ids ) {
				foreach ( $selected_products_ids as $product_id ) {
					echo Webfacing_Public_Post_Preview::get_preview_link( get_post( $product_id ) ), PHP_EOL;
				}
			}
			if ( $selected_coupon_id ) {
				echo 'Rabattkode: <code>' . get_post( $selected_coupon_id )->post_title . '</code>' . PHP_EOL;
			}
			echo PHP_EOL, '</pre>';
		}
	}
	
	public static function send_sms( string $title, array $customer_data, array $selected_customers_emails, array $selected_pages_ids, array $selected_products_ids, $selected_coupon_id, string $text1, string $text2, bool $debug ) {
		$current_user = wp_get_current_user();
//		$selected_customers_emails = $debug ? [ $current_user->user_email ] : $selected_customers_emails;
		$sent = 0;
		$fail = 0;
		foreach ( $selected_customers_emails as $customer_email ) {
			if ( $customer_email ) {
				$customer_name = $customer_data[ $customer_email ]['name'];
				$customer_sms = $debug ? get_user_meta( get_current_user_id(), 'billing_phone', true ) : $customer_data[ $customer_email ]['phone'];
				$customer_sms = str_replace( ' ', '', $customer_sms );
				$customer_sms = strlen( $customer_sms ) < 10 ? '47' . $customer_sms : $customer_sms;
				$customer_sms = $customer_sms[0] != '+' ? '+' . $customer_sms : $customer_sms;
				$customer_sms = strlen( $customer_sms ) < 11 ? false : $customer_sms;
				$message  = '';
				$message .= PHP_EOL . ( $selected_pages_ids || $selected_products_ids || $selected_coupon_id ?
					'Tilbud fra ' . get_option( 'woocommerce_email_from_name', get_bloginfo() ) . ' om ' . $title . '.' :
					'Viktig melding til kursdeltakere:' ) . PHP_EOL;
				$message .= PHP_EOL . 'Hei ' . $customer_name . PHP_EOL;
				$message .= $text1 ? PHP_EOL . $text1 . PHP_EOL : '';
				if ( $selected_pages_ids || $selected_products_ids ) {
					$message .= PHP_EOL . 'Kurs du nå kan forhåndspåmelde deg til:' . PHP_EOL;
					foreach ( $selected_products_ids as $product_id ) {
						$message .= PHP_EOL . get_the_title( $product_id ) . ' ' . Webfacing_Public_Post_Preview::get_preview_link( get_post( $product_id ) ) . PHP_EOL;
					}
					$count = $selected_products_ids ? count( $selected_products_ids ) : 0;

	//				$message .= 'Obs: Lenken' . ( $count == 1 ? '' : 'e' ) . ' over virker kun til ' . date_i18n( 'l d.m.y \k\l 24', strtotime( '+' . ( Kursoversikt::$preview_life - 1 ) . ' days', current_time( 'U' ) ) ) . '.' . PHP_EOL;
					foreach ( $selected_pages_ids as $page_id ) {
						$message .= PHP_EOL . get_the_title( $page_id )    . ' ' . Webfacing_Public_Post_Preview::get_preview_link( get_post( $page_id    ), 2 ) . PHP_EOL;
					}
					$message .= PHP_EOL . 'Velkommen tilbake til kurs igjen hos oss neste sesong!' . PHP_EOL;
				}
				if ( $selected_coupon_id ) {
					$message .= PHP_EOL . 'Rabattkode: ' . get_post( $selected_coupon_id )->post_title . PHP_EOL;
				}
				$message .= $text2 ? PHP_EOL . $text2 . PHP_EOL : '';
				$message .= PHP_EOL . 'Med vennlig hilsen' . PHP_EOL . $current_user->display_name . PHP_EOL . get_option( 'woocommerce_email_from_name', get_bloginfo() );
	//			$args = [
	//				'number_to'   => $customer_sms,
	//				'message'     => $message,
	//				'logging'     => 1,
	//				'url_shorten' => 1,
	//			];
				if ( $customer_sms ) {
	//				$response = twl_send_sms( $args );
					try {
						$response = wc_twilio_sms()->get_api()->send( $customer_sms, $message, 'NO' );
						$ok = true;
						$err = '';
					} catch ( Exception $e ) {
						$ok = false;
						$err = $e->getMessage();
					}
	//				$ok  = ! is_wp_error( $response );
	//				$err = ! $ok ? $response->errors['api-error'][0] : '';
				} else {
					$ok = false;
					$err = 'Mobilnummer mangler eller har feil format';
				}
				echo PHP_EOL, '<ol>';
				if ( $ok ) {
					if ( class_exists( 'EmailLog\Core\EmailLogger' ) ) {
						( new \EmailLog\Core\EmailLogger )->log_email( [
							'to'      => $customer_name . ' &lt;' . $customer_sms . '&gt;',
							'subject' => 'SMS fra ' . wp_get_current_user()->display_name,
							'message' => $message,
						] );
					}
					$sent++;
				} else {
					$fail++;
					echo PHP_EOL, '<li>Kunne ikke sende til ', $customer_sms, ' (', $customer_name, ') <small>&laquo;', $err, '&raquo;</small></li>';
				}
				echo PHP_EOL, '<ol>';
				echo PHP_EOL, '<p>', $sent, ' SMS', $sent != 1 ? 'er' : '', ' sendt', $fail ? ', <span style="color: orangered;">og ' . $fail . ' fikk feil</span>' : '';
				echo PHP_EOL, $sent ? ' &nbsp; Se <a href="admin.php?page=email-log">E-post/SMS-loggen</a>.' : '';
				echo '</<p>';
			}
		}
	}

	public function admin_head() {
?>
		<script>
			jQuery( document ).ready( function() {
				jQuery( 'select#event' ).on( 'click', function() {
					if ( jQuery( this ).find( ':selected' ).text() == '-- velg alle kurs --' ) {
						if ( jQuery( this ).attr( 'data-select' ) == 'false' )
							jQuery( this ).attr( 'data-select', 'true'  ).find( 'option[value][value!="-1"]' ).prop( 'selected', true  );
						else
							jQuery( this ).attr( 'data-select', 'false' ).find( 'option' ).prop( 'selected', false );
					}
				} );
				jQuery( 'select#customers' ).on( 'click', function() {
					if ( jQuery( this ).find( ':selected' ).text() == '-- velg alle --' ) {
						if ( jQuery( this ).attr( 'data-select' ) == 'false' )
							jQuery( this ).attr( 'data-select', 'true'  ).find( 'option' ).prop( 'selected', true  );
						else
							jQuery( this ).attr( 'data-select', 'false' ).find( 'option' ).prop( 'selected', false );
					}
				} );
			} );
		</script>
<?php
	}
	
	public static function menu() {
		add_dashboard_page ( 'Forhåndsbestilling', 'Forhåndsbestilling', 'edit_shop_orders', 'preorder',  [ __CLASS__, 'page' ] );
		add_management_page( 'Forhåndsbestilling', 'Forhåndsbestilling', 'edit_shop_orders', 'preorder',  [ __CLASS__, 'page' ] );
	}

	public static function save_post( $post_id, $post ) {
		if ( get_post_type( $post ) == 'page' ) {
			if ( isset( $_POST[ Kursoversikt::$pf . 'links-active' ] ) ) {
				update_post_meta( $post_id, Kursoversikt::pf . 'links_active', ! empty( $_POST[ Kursoversikt::$pf . 'links-active' ] ) );
			} else {
				delete_post_meta( $post_id, Kursoversikt::pf . 'links_active' );
			}
		}
	}

	public static function init() {
		add_action( 'admin_menu', [ 'Kursoversikt_Preorder', 'menu' ] );
		add_action( 'admin_head', [ __CLASS__, 'admin_head' ] );
		add_action( 'save_post',  [ 'Kursoversikt_Preorder', 'save_post' ], 10, 2 );
		
//		add_filter( 'user_contactmethods', function( $methods ) {
//			$methods['billing_phone'] = 'Mobiltelefon';
//			return $methods;
//		} );
		
		add_filter( 'twl_settings_tabs', function( $tabs) {
			$tabs['preorder'] = 'Forhåndsbestilling';
			return $tabs;
		} );

		add_action( 'twl_display_tab', function( $tab ) {
			if ( $tab == 'preorder' ) {
				echo PHP_EOL, '<p>Tilby <a href="tools.php?page=preorder">Forhåndsbestilling av kurs</a></p>';
			}
		} );
	}
}

add_action( 'plugins_loaded', [ 'Kursoversikt_Preorder', 'init' ] );