<?php
function webfacing_events_count_orders( int $customer_id, string $status, int $current_order_id, string $from_date ): int {
	$args = [
		'status'         => $status,
		'customer_id'    => $customer_id,
		'date_completed' => '>=' . $from_date,
		'exclude'        => [ $current_order_id ],
		'return'         => 'ids',
	];
	$orders = wc_get_orders( $args );
	return count( $orders );
}

$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : ( isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : false );
$order = $order_id ? wc_get_order( $order_id ) : false;
$user_id  = is_user_logged_in() && current_user_can( 'read' ) ? get_current_user_id() : false;
$user = get_user_by( 'id', $user_id );
$customer_id = $order ? $order->get_customer_id() : false;
$not_expired = current_time( 'U' ) < Kursoversikt::$refund_req_to || current_user_can( 'edit_shop_orders' ) || $order->get_status() == 'processing';
if ( $not_expired && $user_id && $customer_id && ( in_array( $order->get_status(), [ 'completed', 'processing' ] ) ) && $order->is_paid() && $order->get_date_completed()->getTimestamp() >= Kursoversikt::$refund_c_from && ( $customer_id === $user_id || current_user_can( 'edit_others_shop_orders' ) ) ) {
	$values = [ 'Ingen (ytterligere) refusjon/rabatt', 'Delvis refusjon', 'Rabattkupong 50% til høstkurs', 'Avbestill - full refusjon' ];
	if ( $_SERVER['REQUEST_METHOD'] == 'POST' && isset( $_POST['order_id'] ) ) {
		$item_count = 0;
		$coupons = [];
		$change_status = in_array( $values[2], $_POST['ref'] ) || in_array( $values[3], $_POST['ref'] );
		foreach ( $_POST['ref'] as $order_item_id => $value ) {
			if ( $change_status ) {
				if ( $value == $values[2] ) {
					$quant = $order->get_item_meta( $order_item_id, '_qty', true);
					$item_count_s = $item_count == 0 ? '' : '-x'. $item_count;
					$order_count = webfacing_events_count_orders( $customer_id, 'processing', $order_id, Kursoversikt::$refund_c_from );
					$order_count_s = $order_count == 0 ? '' : '-' . $order_count;
					$coupon = $quant . 'xcovid-19' . $order_count_s . $item_count_s;
					wc_add_order_item_meta( $order_item_id, 'coupon', date_i18n( 'd.m.y' ) . ' ' . $coupon );
//					$coupons[] = '<code>' . esc_attr( $coupon ) . '</code>';
					$coupons[] = esc_attr( $coupon );
					$item_count++;
				} else {
					wc_add_order_item_meta( $order_item_id, 'refund', date_i18n( 'd.m.y' ) . ' ' . $value );
				}
			} else { 
				wc_update_order_item_meta( $order_item_id, 'refund', date_i18n( 'd.m.y' ) . ' ' . $value, wc_get_order_item_meta( $order_item_id, 'refund' ) );
			}
		}
		if ( $change_status ) {
			$order->update_status(
				in_array( $values[3], $_POST['ref'] ) ? 'on-hold' : 'processing',
				esc_attr( implode( ', ', $_POST['ref'] ) ) . implode( ', ', $coupons ) . '.' . PHP_EOL . 'Innsendt av bruker ' . $user->display_name . ' (#' . $user_id . ' &ndash; ' . $user->user_email . ').' . PHP_EOL
			);
			if ( WP_DEBUG ) {
				wp_mail( /*'lill.andersen76@gmail.com,*/'knutsp@gmail.com', '[' . get_bloginfo() . '] Refusjonsvalg', 'Debug' . PHP_EOL . 'Refusjon: ' . print_r( $_POST, true ) . 'Rabattkode: ' . print_r( $coupons, true ) . $order->get_edit_order_url() );
			} else {
				$emails = [ $user->user_email, $order->get_billing_email() ];
				$emails = array_unique( array_map( 'strtolower', $emails ) );
				$emails = implode( ',', $emails );
				wp_mail( $emails, '[' . get_bloginfo() . '] Refusjonsvalg mottatt', 'Påmelding ' . $order_id . ': ' . PHP_EOL . esc_attr( implode( ', ', $_POST['ref'] ) ) . PHP_EOL . esc_attr( implode( ', ', $coupons ) ) );
			}
		} // else ignore $values[0] selection.
		echo PHP_EOL, '<p style="text-align: center;">Takk. Ditt valg er mottatt, vil effektueres så snart som mulig, og en enkel e-postbekreftelse er sendt til ', $emails, '. <br />Eventuell rabattkupong blir også med når du får invitasjon til forhåndspåmelding til høstkurs. <a href="', wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ), '" title="I tilfelle du har flere påmeldinger.">Tilbake til dine påmeldinger</a>.</p>';
	} else {
?>
<div style="margin: auto;<?=wp_is_mobile()?'':' width: 67%; background-color: white; padding: 1em;'?>">
	<form action="<?=get_the_permalink($GLOBALS['post'])?>" method="post">
		<input type="hidden" name="order_id" value="<?=$order->get_id()?>" />
		<table style="width: 100%">
			<caption title="Opprettet <?=date_i18n('d.m.y H:i',strtotime(@$order->get_date_created()))?>. Fullført <?=date_i18n('d.m.y H:i',strtotime(@$order->get_date_completed()))?>. Via <?=get_bloginfo()?> <?=@$order->get_created_via()?>. Med <?=get_browser_name($order->get_customer_user_agent())?><?=wp_is_mobile()?' mobil':''?>. Fra IP <?=@$order->get_customer_ip_address()?>. Transaksjons-ID <?=@$order->get_transaction_id()?>.">
				<strong style="font-weight: bold;">Påmelding nr <?=$order_id?> fra <a href="mailto:<?=$order->get_billing_email()?>" title="E-post <?=$order->get_billing_email()?>."><?=$order->get_billing_first_name()?> <?=$order->get_billing_last_name()?></a>, betalt <?=@$order->get_formatted_order_total()?> via <?=ucfirst(@$order->get_payment_method())?>, <?=date_i18n('l d.m.y \k\l H:i',strtotime(@$order->get_date_paid()))?></strong>
			</caption>
			<thead>
				<tr>
					<th style="width: 8%;"></th>
					<th style="text-align: left; width: 45%; text-transform: none; font-weight: bold; font-size: xx-large;" title="Ordrelinjer.">Kurs</th>
					<th style="text-align: left; width: 47%; text-transform: none; font-weight: bold;" title="Standard valg er ingen refusjon/rabatt.">Valg: <em><?=$values[0]?></em>, <!--?=$values[1]?>,--><em><?=$values[2]?></em>, eller <em><?=$values[3]?></em></th>
				</tr>
			</thead>
			<tbody>
<?php
		$order_items = $order->get_items();
		foreach ( $order_items as $order_item_id => $order_item ) {
			if ( $order_item->is_type( 'line_item' ) ) {
				$order_item_product = new WC_Order_Item_Product( $order_item_id );
				$participants = '';
				$meta_data = $order_item->get_formatted_meta_data();
				foreach ( $meta_data as $participant ) {
					$participants .= strip_tags($participant->display_key . ': ' . $participant->display_value );
				}
				$item_name = str_replace( [ '/ ', 'kl ', 'dag', 'april ', 'uke 16-24 ', 'uke 16-25 ', '2020 ', ':00' ], '', $order_item->get_name() );
?>
				<tr>
					<td><?=wp_is_mobile()?'':get_the_post_thumbnail($order_item_product->get_product_id(),[100,100])?></td>
					<td title="<?=$participants?>."><?=$item_name?> &times;&nbsp;<?=$order_item->get_quantity()?>&nbsp;=
						<abbr title="Opprinnelig kr <?=$order_item_product->get_subtotal()?>,&ndash;.">&nbsp;kr&nbsp;<?=$order_item_product->get_total()?></abbr>,&ndash;
					</td>
					<td style="vertical-align: middle;">
						<label style="white-space: nowrap;" title="<?=$values[0]?>. Takk. Det er til stor hjelp for <?=get_bloginfo()?> i en vanskelig tid.">
							<input type="radio" name="ref[<?=$order_item_id?>]" checked="checked" value="<?=$values[0]?>" /> ingen refusjon/rabatt &nbsp;
						</label>
						<!--label style="white-space: nowrap;" title="<?=$values[1]?>. Greit. Det er forståelig, og vi må forsøke klare oss gjennom dette.">
							<input type="radio" name="ref[<?=$order_item_id?>]" value="<?=$values[1]?>" /> delvis refusjon nå &nbsp;
						</label-->
						<label style="white-space: nowrap;" title="<?=$values[2]?>. Godt. Det vil være til hjelp for <?=get_bloginfo()?> at du velger dette fremfor å avbestille og få full refusjon nå.">
							<input type="radio" name="ref[<?=$order_item_id?>]" value="<?=$values[2]?>" /> rabattkupong &nbsp;
						</label>
						<label style="white-space: nowrap;" title="<?=$values[3]?>. Greit. Påmeldingen blir refundert og kansellert, og du får melding når det er gjort.">
							<input type="radio" name="ref[<?=$order_item_id?>]" value="<?=$values[3]?>" /> avbestill
						</label>
					</td>
				</tr>
<?php
			}
		}
?>
			</tbody>
			<tfoot title="Send inn også om du ikke velger/ønsker refusjon.">
				<tr>
					<td colspan="3" style="text-align: center;">Kun for denne påmeldingen: &nbsp;
						<button type="submit">Send inn og registrer refusjonsvalget</button>
						<address style="display: inline; font-style: italic; background-color: white; color: blue; border-bottom: solid 1px gray;" title="Brukernavn <?=$user->user_login?>. E-post 	<?=$user->user_email?>. Telefon <?=get_user_meta($user_id,'billing_phone',true)?>."> &nbsp;
							<span title="Din IP-adresse <?=$_SERVER['REMOTE_ADDR']?>."><?=get_user_meta($user_id,'billing_city',true)?></span>,
							<span title="Nå: <?=date_i18n( 'l j. F Y \k\l H:i', current_time('U') )?>."><?=date_i18n( 'j/n-y', current_time('U') )?></span>,
							<a style="color: inherit;" href="mailto:<?=$user->user_email?>" title="E-post <?=$user->user_email?>."><?=$user->display_name?></a> &nbsp;
						</address>
					</td>
				</tr>
			</tfoot>
		</table>
		<p>Ditt valg vil bli registert straks. Eventuell rabattkupong kommer med invitasjon til forhåndspåmeling til høstens kurs.</p>
	</form>
</div>
<?php
	}
} else {
	echo PHP_EOL, '<p style="text-align: center;"><strong>Feil: Ugyldig påmelding nr ', $order_id, ' for dette formål. Allerede sendt inn? Vennligst <a href="', wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ), '">gå tilbake</a> og prøv igjen eller en annen påmelding. Meld fra om det gjentar seg uten grunn.</strong></p>';
	error_log( 'User ' . $user_id . ': Failed refund form for order: GET=' . $_GET['order_id'] . ', POST=' . $_POST['order_id'] . ', UA=' . $_SERVER['HTTP_USER_AGENT'] );
}
