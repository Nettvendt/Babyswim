<?php
/**
 *
 */
class Kursoversikt_Preorder {

	const line_height = 18.36;	// px

	public static function order_events( array $events ): array {
		$ordered_events = [];
		echo '<pre>';
		foreach( $events as $event ) {
			$key = '';
			$terms = get_the_terms( $event, Kursoversikt::$woo_loc_tax );
			$term_slug = isset( $terms[0] ) && $terms[0]->parent == 0 ? $terms[0]->slug : '-';
			$key .= str_pad( substr( $term_slug, 0, 2 ), 2, '_' );
			$terms = get_the_terms( $event, Kursoversikt::$woo_event_tax );
			$term_slug = isset( $terms[0] ) && $terms[0]->parent != 0 ? $terms[0]->slug : '-';
			$term_slug = explode( '-', $term_slug )[0];
			$key .= str_pad( $term_slug, 2, '0', STR_PAD_LEFT );
			$time = Kursoversikt::get_event_start_time( $event );
			$key .= substr( date( 'ymdHi', $time ), 0, -1 );
//			var_dump( $key );
			$ordered_events[ $key ] = $event;
		}
		echo '</pre>';
		ksort( $ordered_events, SORT_STRING );
		return count( $ordered_events ) == count( $events ) ? $ordered_events : $events;
	}
	
	public static function page() {
		if ( is_admin() && current_user_can( 'read_private_pages' ) ) {
			$title   = get_admin_page_title();
			$is_post = $_SERVER['REQUEST_METHOD'] == 'POST';
			$selected_age = $is_post && isset( $_POST['age'] ) ? intval( $_POST['age'] ) : false;
			$selected_events = $is_post && isset( $_POST['events'] ) && is_array( $_POST['events'] ) && count( $_POST['events'] ) ? array_map( 'intval', $_POST['events'] ) : false;
			if ( $selected_events[0] <= 0 && count( $selected_events ) > 1 ) {
				unset ( $selected_events[0] );
			}
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
				'meta_value_date' => date( 'Y-m-d', $start ),
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
					foreach ( $order_ids as $order_id ) {
						$order = wc_get_order( $order_id );
						if ( method_exists( $order, 'get_billing_email' ) ) {
							$customer_email = sanitize_email( $order->get_billing_email() );
							$customer_name  = esc_attr( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
							$customer_phone = esc_attr( $order->get_billing_phone() );
							if ( $customer_email ) {
								$customer_data[ $customer_email ] = [ 'name' => $customer_name, 'phone' => $customer_phone, 'count' => true ];
							}
						}
					}
				}
			} else {
				$roles = [ 'instructor' ];
				$roles = $selected_events[0] == -1 ? $roles : $roles[] = 'customer';
//				$customers = get_users( [ 'role__in' => [ 'customer', 'instructor' ] ] );
				$customers = get_users( [ 'role__in' => $roles ] );
				foreach ( $customers as $customer ) {
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
		<p>Lenkene du sender varer kun <?=Kursoversikt::$preview_life?> dager <span class="description">(<a href="options-general.php?page=<?=Kursoversikt::$pf?>page#preview-life">Innnstillinger for offentlig forhåndsvisning</a>).<br /></p>
		
		<form action="<?=admin_url(get_current_screen()->parent_file.'?page='.esc_attr($_GET['page']))?>#customers" method="post">
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
				<select id="event" name="events[]" multiple="multiple" style="min-height: <?=(count($events)+1)*self::line_height?>px;">
					<option<?=selected(empty($selected_events)||$selected_events[0]===0,true,false)?>>-- kun registrerte kunder og instruktører --</option>
					<option<?=selected($selected_events[0]==-1,true,false)?> value="-1">-- kun instruktører --</option>
<?php
			foreach ( $events as $event ) {
				$terms = get_the_terms( $event, Kursoversikt::$woo_event_tax );
				$term = $terms && is_array( $terms ) && count( $terms) == 1 ? $terms[0] : false;
				if ( $term && $term->parent == Kursoversikt::$woo_event_cat[ Kursoversikt::$woo_event_tax ] ) {
					$event_id = intval( $event->ID );
					$count = count( Kursoversikt_Deltakere::get_order_ids_by_product( $event_id, [ 'completed' ] ) );
					echo PHP_EOL, '<option value="', $event_id, '"', $selected_events ? selected( in_array( $event_id, is_array( $selected_events ) ? $selected_events : [] ), true, false ) : '', disabled( $count, 0, false ), '>', $event->post_title, ' (', $count, ')</option>';
				}
			}
?>
				</select>
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
				<select id="customers" multiple="multiple" name="customers[]" style="min-height: <?=(count($customer_data)+1)*self::line_height?>px; min-width: 14em;">
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
				<label for="pages" title="Antall: <?=count($pages)?>.">Oversikt-side (å ta med lenke til)</label><br />
				<select id="pages" name="pages[]" multiple="multiple" style="min-width: 14em; min-height: <?=count($pages)*self::line_height?>px;">
<?php
				foreach ( $pages as $page ) {
					$page_id = intval( $page->ID );
					if ( get_page_template_slug( $page_id ) == 'templates/oceanwp-calendar.php' || has_shortcode( $page->post_content, 'kursoversikt' ) || has_block( 'babyswim/kursoversikt', $page_id ) ) {
?>
					<option value="<?=$page->ID?>"<?=selected(in_array($page_id,is_array($selected_pages_ids)?$selected_pages_ids:[]),true,false)?> title="Dato: <?=get_the_date('',$page)?>"><?=get_the_title($page)?></option>
<?php
					}
				}
?>
				</select>
<?php
//				if ( $selected_events[0] == -1 ) {
?>
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
					<option value="<?=$coupon_id?>"<?=selected($selected_coupon_id,$coupon_id,false)?> title="<?=get_the_title($coupon_id)?>"><?=mb_substr(esc_html(get_the_excerpt($coupon_id)),0,16)?>: <code><?=$value?></code></option>
<?php
					}
?>
				</select>
<?php
//				}
?>
			</p>
			<p style="float: left;">
				<label for="products" title="Antall: <?=count($products)?>.">Alle ventende enkeltkurs (å ta med lenke til)</label><br />
				<select id="products" multiple="multiple" name="products[]" style="min-width: 160px; min-height: <?=(count($products)+1)*self::line_height?>px;">
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
				<label for="text1">Ekstra tekst over lenkene:</label><br />
				<textarea id="text1" name="text1" cols="90" rows="1"><?=isset($_POST['text1'])?esc_textarea($_POST['text1']):''?></textarea>
				<br clear="left" />
				<label for="text2">Ekstra tekst under lenkene:</label><br />
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
//				if ( function_exists( 'twl_send_sms' ) ) {
				if ( function_exists( 'wc_twilio_sms' ) ) {
					echo ' &nbsp; ';
					submit_button( $sms_text, 'large', 'submit', false );
					echo ' &nbsp; ';
					echo PHP_EOL, '<a href="admin.php?page=wc-settings&tab=twilio_sms#wc_twilio_sms_test_mobile_number">Til testsending av SMS-melding til valgt nr</a>.';
				}
 ?>
				<input type="hidden" name="age" value="<?=$selected_age?>" />
<?php
	 			foreach ( $selected_events as $selected_event ) {
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
							$customer_name  = $customer_data[ $customer_email ]['name'];
							$customer_phone = $customer_data[ $customer_email ]['phone'];
							$all_emails[] = '<a href="mailto:' . ( $debug ? $current_user->user_email : $customer_email ) . '" title="Telefon: ' . $customer_phone . '">' . $customer_name . '</a>'/* . ( empty( $customer_phone ) ? ' &nbsp; <small>(OBS: mobilnummer mangler)</small>' : '')*/;
						}
						echo implode( ', ', $all_emails ), '</h3>';
						$subject = $selected_pages_ids || $selected_products_ids ? 'Tilbud om ' . strtolower( $title ) : 'Melding til kursdeltakere';
?>
		<h4>Emne: [<?=get_bloginfo()?>] <?=$subject?></h4>
		<div style="border: 1px solid darkgray; width: 60em; padding: 0 1em 1em;">
		<p>Kjære kunde og kursdeltaker</p>
		<p><?=esc_html($_POST['text1'])?></p>
<?php
						if ( $selected_pages_ids ) {
?>
		<h4>Kurs du nå kan forhåndspåmelde deg til:</h4>
		<ul style="list-style-type: disc; list-style-position: inside;">
<?php
							foreach ( $selected_pages_ids as $page_id ) {
								echo PHP_EOL, '<li><a href="', Webfacing_Public_Post_Preview::get_preview_link( get_post( $page_id ) ), '">', get_the_title( $page_id ), '</a></li>';
							}
?>
		</ul>
<?php
						}
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
						}
						if ( $selected_pages_ids || $selected_products_ids ) {
							$count = ( $selected_pages_ids ? count( $selected_pages_ids ) : 0 ) + ( $selected_products_ids ? count( $selected_products_ids ) : 0 );
?>				
		<p><strong>Obs:</strong> Lenken<?=$count==1?'':'e'?> varer kun <?=Kursoversikt::$preview_life?> dager.</p>
		<p>Velkommen til kurs også neste sesong!</p>
<?php
						}
						if ( $selected_coupon_id ) {
?>
		<p>Rabattkode: <code><?=esc_attr(get_post($selected_coupon_id)->post_title)?></code></p>
<?php
						}
?>
		<p><?=esc_html($_POST['text2'])?></p>
		<p>Med vennlig hilsen<br />
		<?=$current_user->display_name?><br />
		<?=get_bloginfo()?>
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
		}
	}
	
	public static function send_email( string $title, array $customer_data, array $selected_customers_emails, array $selected_pages_ids, array $selected_products_ids, int $selected_coupon_id, string $text1, string $text2, bool $debug ) {
		$current_user = wp_get_current_user();
		$to = [];
		foreach ( $selected_customers_emails as $customer_email ) {
			$customer_name = $customer_data['name'];
			$to[] = $customer_name . ' <' . $customer_email . '>';
		}
		$to       = $debug ? $current_user->user_email : implode( ',', $to );
//		Line to be removed:
//		$to       = $current_user->user_login == 'knutsp' ? 'knutsp+babyswim@gmail.com' : $to;
		$subject  = '[' . get_bloginfo(). '] ' . ( $selected_pages_ids || $selected_products_ids || $selected_coupon_id ?
			'Tilbud om ' . $title :
			'Viktig melding til kursdeltakere' );
		$message  = '<p>Kjære kunde og kursdeltaker</p>' . PHP_EOL;
		$message .= '<p>' . $text1 . '</p>' . PHP_EOL;
		if ( $selected_pages_ids || $selected_products_ids ) {
			$message .= '<h4>Kurs du nå kan forhåndspåmelde deg til:</h4>';
		}
		if ( $selected_pages_ids ) {
			$message .= '<ul>' . PHP_EOL;
			foreach ( $selected_pages_ids as $page_id ) {
				$message .= '<li><a href="' . Webfacing_Public_Post_Preview::get_preview_link( get_post( $page_id ) ) . '">' . get_the_title( $page_id ) . '</a></li>' . PHP_EOL;
			}
			$message .= '</ul>' . PHP_EOL;
		}
		if ( $selected_products_ids ) {
			$message .= '<ul>' . PHP_EOL;
			foreach ( $selected_products_ids as $product_id ) {
				$message .= '<li><a href="' . Webfacing_Public_Post_Preview::get_preview_link( get_post( $product_id ) ) . '">' . get_the_title( $product_id ) . '</a></li>' . PHP_EOL;
			}
			$message .= '</ul>' . PHP_EOL;
		}
		if ( $selected_pages_ids || $selected_products_ids ) {
			$count = ( $selected_pages_ids ? count( $selected_pages_ids ) : 0 ) + ( $selected_products_ids ? count( $selected_products_ids ) : 0 );
			$message .= '<p>Obs: Lenken' . ( $count == 1 ? '' : 'e' ) . ' varer kun ' . Kursoversikt::$preview_life . ' dager.</p>';
			$message .= '<p>Velkommen til kurs hos oss også neste sesong!</p>' . PHP_EOL;
		}
		if ( $selected_coupon_id ) {
			$message .= '<p>Rabattkode: ' . get_post( $selected_coupon_id )->post_title .'</p>'. PHP_EOL;
		}
		$message .= '<p>' . $text2. '</p>' . PHP_EOL;
		$message .= '<p>-- <br />Med vennlig hilsen<br />' . $current_user->display_name . '<br />' . get_bloginfo() . '<br />'. PHP_EOL;
		$headers = [ 'Content-type: text/html; charset=UTF-8' ];
		$ok = wp_mail( $to, $subject, $message, $headers );
		echo PHP_EOL, '<p>E-post ' . ( $ok ? '' : '<strong>ikke</strong> ' ) . 'sendt! &nbsp; Se <a href="admin.php?page=email-log">e-postloggen</a>.</p>';

	}

	public static function export_email( array $customer_data, array $selected_customers_emails, array $selected_pages_ids, array $selected_products_ids, int $selected_coupon_id ) {
		$all_emails = [];
		foreach ( $selected_customers_emails as $customer_email ) {
			$customer_name = $customer_data[ $customer_email ]['name'];
			$all_emails[] = $customer_name . ' &lt;'.  $customer_email . '&gt;';
		}
		if ( $selected_customers_emails ) {
			echo PHP_EOL, '<p><strong>Valgte mottakere:</strong> Kopier til til/adressefeltet i din egen app:<pre style="border: solid black 1px; padding: .5em; background-color: white; width: 40%;">', implode( ',' . PHP_EOL, $all_emails ), '</pre>';
		}
		echo PHP_EOL, '<p><strong>Forslag til emnefelt:</strong> Kopier til til/emnefeltet i din egen app:<pre style="border: solid black 1px; padding: .5em; background-color: white; width: 40%;">[', get_bloginfo(), '] Melding til kursdeltakere</pre></p>';
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
			$customer_name = $customer_data[ $customer_email ]['name'];
			$customer_sms = $debug ? get_user_meta( get_current_user_id(), 'billing_phone', true ) : $customer_data[ $customer_email ]['phone'];
			$customer_sms = str_replace( ' ', '', $customer_sms );
			$customer_sms = strlen( $customer_sms ) < 10 ? '47' . $customer_sms : $customer_sms;
			$customer_sms = $customer_sms[0] != '+' ? '+' . $customer_sms : $customer_sms;
			$customer_sms = strlen( $customer_sms ) < 11 ? false : $customer_sms;
			$message  = '';
			$message .= PHP_EOL . ( $selected_pages_ids || $selected_products_ids || $selected_coupon_id ?
				'Tilbud fra ' . get_bloginfo() . ' om ' . $title :
				'Viktig melding til kursdeltakere' ) . ':' . PHP_EOL;
			$message .= PHP_EOL . 'Hei ' . $customer_name . PHP_EOL;
			$message .= $text1 ? PHP_EOL . $text1 . PHP_EOL : '';
			if ( $selected_pages_ids || $selected_products_ids ) {
				$message .= PHP_EOL . 'Kurs du nå kan forhåndspåmelde deg til:' . PHP_EOL;
				foreach ( $selected_pages_ids as $page_id ) {
					$message .= PHP_EOL . get_the_title( $page_id )    . ' ' . Webfacing_Public_Post_Preview::get_preview_link( get_post( $page_id    ) ) . PHP_EOL;
				}
				foreach ( $selected_products_ids as $product_id ) {
					$message .= PHP_EOL . get_the_title( $product_id ) . ' ' . Webfacing_Public_Post_Preview::get_preview_link( get_post( $product_id ) ) . PHP_EOL;
				}
				$count = ( $selected_pages_ids ? count( $selected_pages_ids ) : 0 ) + ( $selected_products_ids ? count( $selected_products_ids ) : 0 );

				$message .= PHP_EOL . 'Obs: Lenken' . ( $count == 1 ? '' : 'e' ) . ' varer kun ' . Kursoversikt::$preview_life . ' dager.';
				$message .= PHP_EOL . 'Velkommen til kurs hos oss også neste sesong!' . PHP_EOL;
			}
			if ( $selected_coupon_id ) {
				$message .= PHP_EOL . 'Rabattkode: ' . get_post( $selected_coupon_id )->post_title . PHP_EOL;
			}
			$message .= $text2 ? PHP_EOL . $text2 . PHP_EOL : '';
			$message .= PHP_EOL . 'Med vennlig hilsen' . PHP_EOL . $current_user->display_name . PHP_EOL . get_bloginfo();
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
						'to'      => $customer_sms . ' ' . $customer_name,
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
		}
		echo PHP_EOL, '<p>', $sent, ' SMS', $sent != 1 ? 'er' : '', ' sendt', $fail ? ', <span style="color: orangered;">og ' . $fail . ' fikk feil</span>' : '';
		echo PHP_EOL, $sent ? ' &nbsp; Se <a href="admin.php?page=email-log">E-post/SMS-loggen</a>.' : '';
		echo '</<p>';
	}

	public static function menu() {
		add_dashboard_page ( 'Forhåndsbestilling', 'Forhåndsbestilling', 'edit_shop_orders', 'preorder',  [ __CLASS__, 'page' ] );
		add_management_page( 'Forhåndsbestilling', 'Forhåndsbestilling', 'edit_shop_orders', 'preorder',  [ __CLASS__, 'page' ] );
	}

	public static function init() {
		add_action( 'admin_menu', [ 'Kursoversikt_Preorder', 'menu' ] );
//		add_filter( 'gettext', function( $trans, $text, $dom ) {
//			if ( $dom == TWL_TD ) {
//				if ( $text == 'Mobile Number' ) {
//					$trans = 'Mobilnummer';
//				}
//			}
//			return $trans;
//		}, 10, 3 );
		
		add_filter( 'user_contactmethods', function( $methods ) {
			$methods['billing_phone'] = 'Mobiltelefon';
			return $methods;
		} );
		
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