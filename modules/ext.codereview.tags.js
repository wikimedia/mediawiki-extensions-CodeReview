( function( $ ) {
var $rev = 0;

window.CodeReview = $.extend( window.CodeReview, {

	/* TODO we should probably add that click handling from PHP
	 * ui/CodeRevisionView.php */
	tagInit: function( rev ) {
		$rev = rev;
		$('.codereview-remove-tag').click( function() {
			CodeReview.tagRemove( $(this).attr( 'id' ) )
		});
	},
	tagAdd: function() {

	},
	tagRemove: function( HTMLId ) {
		var tag = HTMLId.replace( /codereview-remove-tag-(.*)/, "$1" );
		$.ajax({
			url: mw.util.wikiScript( 'api' ),
			data: {
				'action': 'coderevisionupdate',

				'repo' : mw.config.get( 'wgCodeReviewRepository' ),
				'rev'  : $rev,

				'removetags': tag,

				'format': 'json',
			},
			dataType: 'json',
			type: 'POST',
			success: function( data ) {
				var remover = $( '#'+HTMLId );
				// remove tag:
				remover.prev().fadeOut().remove();

				/**
				 * tag might be followed by a text node ', '
				 * which can not be reached with jQuery next()
				 */
				var nextNode = remover[0].nextSibling;
				console.log( nextNode );
				if( nextNode.nodeType === 3
					&& nextNode.nodeValue === ", " ) {
					nextNode.parentNode.removeChild( nextNode );
				}
				// finally remove the tag removal sign
				remover.fadeOut().remove();
			},
			error: function() {
				// TODO
			},
		});
	},


}); // window.CodeReview
})( jQuery );
