( function( $ ) {
// Create or extend the object
window.CodeReview = $.extend( window.CodeReview, {

	focusReplyArea : function() {
		var $replyArea = $('.mw-codereview-post-comment textarea:first');
		if ( $replyArea.size() ) {
			$replyArea[0].focus();	
		}
	}

});

$( document ).ready( function() {
	CodeReview.focusReplyArea();
});

})( jQuery );
