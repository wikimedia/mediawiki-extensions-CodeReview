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
			$el.data( 'codeTooltipLoading', true );
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
					if ( !$el.data( 'codeTooltipLeft' ) ) {
						$el.tipsy( 'show' );
					}
				}
			);
		});
		// take care of cases when louse leaves our link while we load stuff from API.
		// We shouldn't display the tooltip in such case.
		$( this ).mouseleave( function( e ) {
			var $el = $( this );
			if ( $el.data( 'codeTooltip' ) || !$el.data( 'codeTooltipLoading' ) ) {
				return;
			}
			$el.data( 'codeTooltipLeft', true );
		});
	});
};

$( document ).ready( CodeTooltipsInit );
