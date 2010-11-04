<?php

// Special:Code/MediaWiki/tag
class CodeTagListView extends CodeView {
	function __construct( $repoName ) {
		parent::__construct();
		$this->mRepo = CodeRepository::newFromName( $repoName );
	}

	function execute() {
		global $wgOut;
		$name = $this->mRepo->getName();
		$list = $this->mRepo->getTagList();

		$tc = new WordCloud( $list, array( $this, 'linkCallback' ) );
		$wgOut->addHTML( $tc->showCloud() );
	}

	public function linkCallback( $tag, $weight ) {
		$query = $this->mRepo->getName() . '/tag/' . $tag;
		return Html::element( 'a', array(
			'href' => SpecialPage::getTitleFor( 'Code', $query )->getFullURL(),
			'class' => 'plainlinks mw-wordcloud-size-' . $weight ), $tag );
	}
}
