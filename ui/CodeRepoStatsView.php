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
				. '<tr><th>' . wfMessage( 'code-field-status' )->escaped() . '</th><th>'
				. wfMessage( 'code-stats-count' )->escaped() . '</th></tr>' );
			$linkRenderer = \MediaWiki\MediaWikiServices::getInstance()->getLinkRenderer();
			foreach ( CodeRevision::getPossibleStates() as $state ) {
				$count = isset( $stats->states[$state] ) ? $stats->states[$state] : 0;
				$count = htmlspecialchars( $wgLang->formatNum( $count ) );
				$link = $linkRenderer->makeLink(
					SpecialPage::getTitleFor( 'Code', $repoName . '/status/' . $state ),
					$this->statusDesc( $state )
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
			$this->writeStatusPathTable( 'fixme', $stats->fixmesPerPath );
		}

		if ( !empty( $stats->newPerPath ) ) {
			$this->writeStatusPathTable( 'new', $stats->newPerPath );
		}
	}

	/**
	 * @param string $status
	 * @param array $array
	 */
	function writeStatusPathTable( $status, $array ) {
		global $wgOut;

		$wgOut->wrapWikiMsg( "<h3 id=\"stats-$status-path\">$1</h3>", "code-stats-$status-breakdown-path" );

		foreach ( $array as $path => $news ) {
			$wgOut->wrapWikiMsg( "<h4 id=\"stats-$status-path\">$1</h4>", array( "code-stats-$status-path", $path ) );
			$this->writeAuthorTable( $status, $news, array( 'path' => $path ) );
		}
	}

	/**
	 * @param string $status
	 * @param array $array
	 */
	function writeAuthorStatusTable( $status, $array ) {
		global $wgOut;
		$wgOut->wrapWikiMsg( "<h3 id=\"stats-{$status}\">$1</h3>", "code-stats-{$status}-breakdown" );
		$this->writeAuthorTable( $status, $array );
	}

	/**
	 * @param string $status
	 * @param array $array
	 * @param array $options
	 */
	function writeAuthorTable( $status, $array, $options = array() ) {
		global $wgOut, $wgLang;

		$repoName = $this->mRepo->getName();
		$wgOut->addHTML( '<table class="wikitable">'
			. '<tr><th>' . wfMessage( 'code-field-author' )->escaped() . '</th><th>'
			. wfMessage( 'code-stats-count' )->escaped() . '</th></tr>' );
		$title = SpecialPage::getTitleFor( 'Code', $repoName . "/status/{$status}" );

		$linkRenderer = \MediaWiki\MediaWikiServices::getInstance()->getLinkRenderer();
		foreach ( $array as $user => $count ) {
			$count = htmlspecialchars( $wgLang->formatNum( $count ) );
			$link = $linkRenderer->makeLink(
				$title,
				$user,
				array(),
				array_merge( $options, array( 'author' => $user ) )
			);
			$wgOut->addHTML( "<tr><td>$link</td>"
				. "<td>$count</td></tr>" );
		}
		$wgOut->addHTML( '</table>' );
	}
}
