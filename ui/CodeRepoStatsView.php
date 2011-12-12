<?php

// Special:Code/MediaWiki/stats
class CodeRepoStatsView extends CodeView {

	function __construct( $repo ) {
		parent::__construct( $repo );
	}

	function execute() {
		global $wgOut, $wgLang;

		$stats = RepoStats::newFromRepo( $this->mRepo );
		$repoName = $this->mRepo->getName();
		$wgOut->wrapWikiMsg( '<h2 id="stats-main">$1</h2>', array( 'code-stats-header', $repoName ) );
		$wgOut->addWikiMsg( 'code-stats-main',
			$wgLang->timeanddate( $stats->time, true ),
			$wgLang->formatNum( $stats->revisions ),
			$repoName,
			$wgLang->formatNum( $stats->authors ),
			$wgLang->time( $stats->time, true ),
			$wgLang->date( $stats->time, true )
		);

		if ( !empty( $stats->states ) ) {
			$wgOut->wrapWikiMsg( '<h3 id="stats-revisions">$1</h3>', 'code-stats-status-breakdown' );
			$wgOut->addHTML( '<table class="wikitable">'
				. '<tr><th>' . wfMsgHtml( 'code-field-status' ) . '</th><th>'
				. wfMsgHtml( 'code-stats-count' ) . '</th></tr>' );
			foreach ( CodeRevision::getPossibleStates() as $state ) {
				$count = isset( $stats->states[$state] ) ? $stats->states[$state] : 0;
				$count = htmlspecialchars( $wgLang->formatNum( $count ) );
				$link = $this->skin->link(
					SpecialPage::getTitleFor( 'Code', $repoName . '/status/' . $state ),
					htmlspecialchars( $this->statusDesc( $state ) )
				);
				$wgOut->addHTML( "<tr><td>$link</td>"
					. "<td class=\"mw-codereview-status-$state\">$count</td></tr>" );
			}
			$wgOut->addHTML( '</table>' );
		}

		if ( !empty( $stats->fixmes ) ) {
			$this->writeAuthorStatusTable( 'fixme', $stats->fixmes );
		}

		if ( !empty( $stats->new ) ) {
			$this->writeAuthorStatusTable( 'new', $stats->new );
		}

		if ( !empty( $stats->fixmesPerPath ) ) {
			$wgOut->wrapWikiMsg( "<h3 id=\"stats-fixme-path\">$1</h3>", 'code-stats-fixme-breakdown-path' );

			foreach ( $stats->fixmesPerPath as $path => $fixmes ) {
				$wgOut->wrapWikiMsg( "<h4 id=\"stats-fixme-path\">$1</h4>", array( 'code-stats-fixme-path', $path ) );
				$this->writeAuthorTable( 'fixme', $fixmes, array( 'path' => $path ) );
			}
		}

		if ( !empty( $stats->newPerPath ) ) {
			$wgOut->wrapWikiMsg( "<h3 id=\"stats-new-path\">$1</h3>", 'code-stats-new-breakdown-path' );

			foreach ( $stats->newPerPath as $path => $news ) {
				$wgOut->wrapWikiMsg( "<h4 id=\"stats-new-path\">$1</h4>", array( 'code-stats-new-path', $path ) );
				$this->writeAuthorTable( 'fixme', $news, array( 'path' => $path ) );
			}
		}
	}

	/**
	 * @param $status string
	 * @param $array array
	 */
	function writeAuthorStatusTable( $status, $array ) {
		global $wgOut;
		$wgOut->wrapWikiMsg( "<h3 id=\"stats-{$status}\">$1</h3>", "code-stats-{$status}-breakdown" );
		$this->writeAuthorTable( $status, $array );
	}

	/**
	 * @param $status string
	 * @param $array array
	 * @param $options array
	 */
	function writeAuthorTable( $status, $array, $options = array() ) {
		global $wgOut, $wgLang;

		$repoName = $this->mRepo->getName();
		$wgOut->addHTML( '<table class="wikitable">'
			. '<tr><th>' . wfMsgHtml( 'code-field-author' ) . '</th><th>'
			. wfMsgHtml( 'code-stats-count' ) . '</th></tr>' );
		$title = SpecialPage::getTitleFor( 'Code', $repoName . "/status/{$status}" );

		foreach ( $array as $user => $count ) {
			$count = htmlspecialchars( $wgLang->formatNum( $count ) );
			$link = $this->skin->link(
				$title,
				htmlspecialchars( $user ),
				array(),
				array_merge( $options, array( 'author' => $user ) )
			);
			$wgOut->addHTML( "<tr><td>$link</td>"
				. "<td>$count</td></tr>" );
		}
		$wgOut->addHTML( '</table>' );
	}
}
