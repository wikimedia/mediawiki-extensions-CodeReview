<?php

class CodeReviewTest extends PHPUnit_Framework_TestCase {
	private function createRepo() {
		$row = new stdClass();
		$row->repo_id = 1;
		$row->repo_name = 'Test';
		$row->repo_path = 'somewhere';
		$row->repo_viewvc = 'http://example.com/view/';
		$row->repo_bugzilla = 'http://example.com/bugzilla/$1';

		return CodeRepository::newFromRow( $row );
	}

	public function testCommentWikiFormatting() {
		$repo = $this->createRepo();
		$formatter = new CodeCommentLinkerWiki( $repo );

		$this->assertEquals( '[http://foo http://foo]', $formatter->link( 'http://foo' ) );
		$this->assertEquals( '[http://example.com/bugzilla/123 bug 123]', $formatter->link( 'bug 123' ) );
		$this->assertEquals( '[[Special:Code/Test/456|r456]]', $formatter->link( 'r456' ) );

		// fails, 18614
		// $this->assertEquals( '[http://foo.bar r123]', $formatter->link( '[http://foo.bar r123]' ) );
		// $this->assertEquals( '[[foo|bug 1234]]', $formatter->link( '[[foo|bug 1234]]' ) );
		// $this->assertEquals( '[[bugzilla:19359|bug 19359]]', $formatter->link( '[[bugzilla:19359|bug 19359]]' ) );
		
		// fails, bug 19299
		// $this->assertEquals( '[[bugzilla:18989|Bug 18989]]', $formatter->link( '[[bugzilla:18989|Bug 18989]]' ) );
		// $this->assertEquals( '[http://www.mediawiki.org/wiki/Special:Code/MediaWiki/75762#code-comments r75762 CR comments]',
		//	$formatter->link( [http://www.mediawiki.org/wiki/Special:Code/MediaWiki/75762#code-comments r75762 CR comments]' ) );

		// fails, bug 24279
		// $this->assertEquals( '[http://svn.wikimedia.org/viewvc/mediawiki/trunk/phase3/includes/api/ApiUpload.php?pathrev=70049&r1=70048&r2=70049 ViewVC]',
		//	$formatter->link( '[http://svn.wikimedia.org/viewvc/mediawiki/trunk/phase3/includes/api/ApiUpload.php?pathrev=70049&r1=70048&r2=70049 ViewVC]' ) );
		// $this->assertEquals( '<nowiki>bug 24027</nowiki>', $formatter->link( '<nowiki>bug 24027</nowiki>' ) );
		// $this->assertEquals( 'http://bugzilla.org/13518#c9', $formatter->link( 'bug 13518#c9' ) );
		// $this->assertEquals( 'http://bugzilla.org/13518#c9', $formatter->link( 'bug 13518 comment 9' ) );

		// $this->assertEquals( '', $formatter->link( '' ) );
	}
}