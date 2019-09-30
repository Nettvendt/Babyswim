( function( blocks, element, components ) {
    var el = element.createElement,
    registerBlockType = blocks.registerBlockType,
    ServerSideRender = components.ServerSideRender;
 
    registerBlockType( 'babyswim/kursoversikt', {
        title: 'Kursoversikt',
        icon: 'calendar',
        category: 'common',
 
        edit: function( props ) {
             return (
                el(ServerSideRender, {
                    block: "babyswim/kursoversikt",
                    attributes: props.attributes
                } )
            );
        },
		save: function() {
			return null;
		}
    } );
}(
    window.wp.blocks,
    window.wp.element,
    window.wp.components,
) );
