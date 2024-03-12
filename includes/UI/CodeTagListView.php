<?php

namespace MediaWiki\Extension\CodeReview\UI;

use Html;
use SpecialPage;

/**
 * Special:Code/MediaWiki/tag
 */
class CodeTagListView extends CodeView {
	public function execute() {
		global $wgOut;
		$list = $this->mRepo->getTagList( true );

		if ( count( $list ) === 0 ) {
			$wgOut->addWikiMsg( 'code-tags-no-tags' );
		} else {
			# Show a cloud made of tags
			$tc = new WordCloud( $list, [ $this, 'linkCallback' ] );
			$wgOut->addHTML( $tc->getCloudHtml() );
		}
	}

	/**
	 * @param string $tag
	 * @param string $weight
	 * @return string
	 */
	public function linkCallback( $tag, $weight ) {
		return Html::element( 'a', [
			'href' => SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() . '/tag/' . $tag )->getFullURL(),
			'class' => 'plainlinks mw-wordcloud-size-' . $weight ], $tag
		) . "\n";
	}
}
