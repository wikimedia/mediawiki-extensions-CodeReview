<?php

// Special:Code/MediaWiki/status
class CodeStatusListView extends CodeView {
	function __construct( $repo ) {
		parent::__construct( $repo );
	}

	function execute() {
		global $wgOut;
		$name = $this->mRepo->getName();
		$states = CodeRevision::getPossibleStates();
		$wgOut->wrapWikiMsg( "== $1 ==", 'code-field-status' );

		// Give grep a chance to find the usages:
		// code-status-new, code-status-fixme, code-status-reverted, code-status-resolved,
		// code-status-ok, code-status-deferred, code-status-old
		$table_rows = '';
		foreach ( $states as $state ) {
			$link = Linker::link(
				SpecialPage::getTitleFor( 'Code', $name . "/status/$state" ),
				wfMessage( "code-status-".$state )->escaped()
			);
			$table_rows .= "<tr><td class=\"mw-codereview-status-$state\">$link</td>"
				. "<td>" . wfMessage( "code-status-desc-" . $state )->escaped() . "</td></tr>\n" ;
		}
		$wgOut->addHTML( '<table class="wikitable">'
			. '<tr><th>' . wfMessage( 'code-field-status' )->escaped() . '</th>'
			. '<th>' . wfMessage( 'code-field-status-description' )->escaped() . '</th></tr>'
			. $table_rows
			. '</table>'
		);
	}
}
