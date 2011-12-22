( function( $ ) {
var $rev = 0;

window.CodeReview = $.extend( window.CodeReview, {

	/* initialization from PHP */
	lcInit: function( rev ) {
		$rev = rev;
		// Register click() event to each diff lines
		$( 'table.mw-codereview-diff tr.commentable').click(
			function () { CodeReview.lcShowForm( $(this) ); }
			);
	},

	/**
	 * Show the line comment code below a line of code
	 * @param lineCode jQuery object
	 */
	lcShowForm: function( lineCode ) {
		// Make sure the line id is an integer:
		var lineNumber = parseInt( lineCode.attr('id') ) + 0;
		// Forge the line comment HTML id:
		var htmlId = 'comment-for-' + lineNumber;

		lineCode.unbind( 'click' );
		lineCode.click( function () {
			$( '#'+htmlId ).fadeOut( 200 ).remove();

			lineCode.unbind( 'click' );
			lineCode.click( function() {
				CodeReview.lcShowForm( lineCode );
			});
		});

		var lineComment =
		$('<tr id="' + htmlId + '"><td colspan="3">'
		+'<textarea id="lineCommentContent" rows="5"></textarea><br/>'
		+'<input id="lineCommentSend" type="button" value="Send">'
		+'</td></tr>');
		lineComment.insertAfter( lineCode );
		$( '#lineCommentContent' ).focus();

		$( '#lineCommentSend' ).click( function() {
			CodeReview.lcSubmitComment(
				lineCode,
				lineComment,
				$( '#lineCommentContent' ).val()
			);
		});
	},

	lcSubmitComment: function( lineCode, lineComment, comment ) {

		// retrieve line number from the code line id attribute:
		var lineId = lineCode.attr('id');
		lineId = lineId.substring( lineId.indexOf( '-' ) + 1);

		$.ajax({
			url: mw.util.wikiScript( 'api' ),
			data: {
				'action': 'coderevisionupdate',

				'repo'  : mw.config.get( 'wgCodeReviewRepository' ),
				'rev'   : $rev,

				'comment': comment,
				'patchline' : lineId,

				'format': 'json',
			},
			dataType: 'json',
			type: 'POST',
			success: function( data ) {
				// our API return usage error as a success!
				if( data.error !== undefined ) {
					console.log( lineComment.find( 'input' ) );
					lineComment.find( 'input' ).after(
						$('<span class="errorbox"></span>')
						.text( data.error.info )
					);
					return;
				}

				var text = data.coderevisionupdate.HTML
				lineComment.fadeOut( 200 ).remove();

				// check we have a list to insert the comment in.
				var next = lineCode.next( '.inlineComment' );
				if( next.length === 0 ) {
					$( '<tr class="inlineComment"><td colspan="3"><ul><li>'+text+'</li></ul></td></tr>' ).insertAfter( lineCode )
				} else {
					lineCode.next('.inlineComment').find( 'ul' ).append(
						$( '<li>' + text + '</li>' )
					);
				}


				// rebind event handler
				lineCode.click(
					function () { CodeReview.lcShowForm( $(this) ); }
				);
			},
			error: function() {
				console.log( "AJAX ERROR" );
			}
		});
	},

}); // window.CodeReview
})( jQuery );
