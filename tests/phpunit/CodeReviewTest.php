<?php

use MediaWiki\Extension\CodeReview\Backend\CodeCommentLinkerWiki;
use MediaWiki\Extension\CodeReview\Backend\CodeRepository;
use MediaWiki\Extension\CodeReview\Backend\CodeRevision;

/**
 * @group CodeReview
 */
class CodeReviewTest extends MediaWikiIntegrationTestCase {
	private function createRepo() {
		$row = new stdClass();
		$row->repo_id = 1;
		$row->repo_name = 'Test';
		$row->repo_path = 'somewhere';
		$row->repo_viewvc = 'http://example.com/view/';
		$row->repo_bugzilla = 'http://example.com/$1';

		return CodeRepository::newFromRow( $row );
	}

	/**
	 * @covers \MediaWiki\Extension\CodeReview\Backend\CodeCommentLinkerWiki
	 */
	public function testCommentWikiFormatting() {
		$repo = $this->createRepo();
		$formatter = new CodeCommentLinkerWiki( $repo );

		$this->assertEquals( '[http://foo http://foo]', $formatter->link( 'http://foo' ) );
		$this->assertEquals( '[http://example.com/123 bug 123]', $formatter->link( 'bug 123' ) );
		$this->assertEquals( '[[Special:Code/Test/456|r456]]', $formatter->link( 'r456' ) );

		// phpcs:disable Generic.Files.LineLength
		// fails, 18614
		// $this->assertEquals( '[http://foo.bar r123]', $formatter->link( '[http://foo.bar r123]' ) );
		// $this->assertEquals( '[[foo|bug 1234]]', $formatter->link( '[[foo|bug 1234]]' ) );
		// $this->assertEquals( '[[bugzilla:19359|bug 19359]]', $formatter->link( '[[bugzilla:19359|bug 19359]]' ) );

		// fails, bug 19299
		// $this->assertEquals( '[https://www.mediawiki.org/wiki/Special:Code/MediaWiki/75762#code-comments r75762 CR comments]',
		//	$formatter->link( '[https://www.mediawiki.org/wiki/Special:Code/MediaWiki/75762#code-comments r75762 CR comments]' ) );

		// fails, bug 24279
		// $this->assertEquals( '[http://svn.wikimedia.org/viewvc/mediawiki/trunk/phase3/includes/api/ApiUpload.php?pathrev=70049&r1=70048&r2=70049 ViewVC]',
		//	$formatter->link( '[http://svn.wikimedia.org/viewvc/mediawiki/trunk/phase3/includes/api/ApiUpload.php?pathrev=70049&r1=70048&r2=70049 ViewVC]' ) );
		// $this->assertEquals( '<nowiki>bug 24027</nowiki>', $formatter->link( '<nowiki>bug 24027</nowiki>' ) );
		// $this->assertEquals( 'http://example.com/13518#c9', $formatter->link( 'bug 13518#c9' ) );
		// $this->assertEquals( 'http://example.com/13518#c9', $formatter->link( 'bug 13518 comment 9' ) );

		// $this->assertEquals( '[[Special:Code/Test/10234#c5]]', $formatter->link( 'r10234#c5' ) );

		// $this->assertEquals( '', $formatter->link( '' ) );
		// phpcs:enable
	}

	/**
	 * @covers \MediaWiki\Extension\CodeReview\Backend\CodeRevision::getCanonicalUrl
	 */
	public function testCommentCanonicalUrl() {
		# Fixture:
		$repo = $this->createRepo();
		$cr = CodeRevision::newFromSvn( $repo, [
			'rev'    => 305,
			'author' => 'hashar',
			'date'   => '15 august 2011',
			'msg'    => 'dumb revision message',
			'paths'  => [ [ 'path' => '/dev/null', 'action' => 'A' ] ],
			]
		);

		# Find out our revision root URL
		$baseUrl = SpecialPage::getTitleFor( 'Code', $repo->getName() . '/305' )->getCanonicalUrl();

		# Test revision URL with various comment id:
		$this->assertEquals( $baseUrl, $cr->getCanonicalUrl( '' ) );
		$this->assertEquals( $baseUrl, $cr->getCanonicalUrl( 0 ) );
		$this->assertEquals( $baseUrl, $cr->getCanonicalUrl( null ) );
		$this->assertEquals( $baseUrl, $cr->getCanonicalUrl( "0" ) );
		$this->assertEquals( $baseUrl . '#c777', $cr->getCanonicalUrl( 777 ) );
		$this->assertEquals( $baseUrl . '#c777', $cr->getCanonicalUrl( "777" ) );
	}
}
