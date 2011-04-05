var CodeTooltipsInit = function() {
	$( 'a[href]' ).each( function() {
		var link = this.getAttribute( 'href' );
		if ( !link ) {
			return;
		}
		var matches = link.match( /^\/.*\/Special:Code\/([-A-Za-z\d_]*?)\/(\d+)$/ );
		if ( !matches ) {
			return;
		}
		$( this ).mouseenter( function( e ) {
			var $el = $( this );
			if ( $el.data('codeTooltip') ) {
				return; // already processed
			}
			var reqData = {
				format: 'json',
				action: 'query',
				list: 'coderevisions',
				crprop: 'revid|message|status|author',
				crrepo: matches[1],
				crrevs: matches[2],
				crlimit: '1'
			};
			$el.tipsy( { fade: true, gravity: 'sw', html:true } );
			$.getJSON(
				mw.config.get( 'wgScriptPath' ) + '/api' + mw.config.get( 'wgScriptExtension' ),
				reqData,
				function( data ) {
					if ( !data || !data.query || !data.query.coderevisions ) {
						return;
					}
					var rev = data.query.coderevisions[0];
					var text = rev['*'].length > 82 ? rev['*'].substr(0,80) + '...' : rev['*'];
					text = text.replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
					text = text.replace( /\n/g, '<br/>' );

					var tip = '<div class="mw-codereview-status-' + rev.status + '" style="padding:5px 8px 4px; margin:-5px -8px -4px;">'
						+ 'r' + matches[2]
						+ ' [' + rev.status + '] by '
						+ rev.author
						+ ( rev['*'] ? ' - ' + text : '' )
						+ '</div>';
					$el.attr( 'title', tip );
					$el.data( 'codeTooltip', true );
					$el.tipsy( 'show' );
				}
			);
		});
	});
};

$( document ).ready( CodeTooltipsInit );
