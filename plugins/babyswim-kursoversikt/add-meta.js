jQuery( document ).ready( function() {
	addEvent();
} );

jQuery( document ).ajaxComplete( function() {
	addEvent();
} );

function addEvent() {
	jQuery( '#woocommerce-order-items .wc-order-edit-line-item .wc-order-edit-line-item-actions a.edit-order-item' ).click( function() {
		var item = jQuery( this ).parents( 'tr.item' ).attr( 'data-order_item_id' );
		jQuery( '#woocommerce-order-items .edit .meta tfoot .add_order_item_meta.button' ).mouseleave( function() {
			quant = setQuant( item );
//			var name = quant == 1 ? 'Deltaker' : 'Tvilling ' + quant;
			var name = 'participant';
			jQuery( '#woocommerce-order-items .edit .meta .meta_items tr[data-meta_id="0"] td input[type="text"]' ).last().val( name );
			jQuery( '#woocommerce-order-items .edit .meta .meta_items tr[data-meta_id="0"] td textarea' ).last().attr( 'placeholder', 'yyyy-mm-dd Fornavn' );
			jQuery( '#woocommerce-order-items .edit .meta .meta_items button.remove_order_item_meta.button' ).mouseleave( function() {
				setQuant( item );
			} );
		} );
	} );
}

function setQuant( item ) {
	var quant = jQuery( '#woocommerce-order-items .item[data-order_item_id="' + item + '"] .edit .meta .meta_items tr:visible[data-meta_id]' ).length;
	jQuery( '#woocommerce-order-items td.quantity input.quantity[name="order_item_qty[' + item + ']"]' ).val( quant );
	return quant;
}