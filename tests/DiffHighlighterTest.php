<?php

class CodeDiffHighlighterTest extends MediaWikiTestCase {

	/**
	 * @dataProvider provideUnifiedDiffChunksDelimiters
	 */
	function testParseChunkDelimiters( $expected, $delimiter ) {
		$this->assertEquals(
			$expected,
			CodeDiffHighlighter::parseChunkDelimiter( $delimiter )
		);
	}

	function provideUnifiedDiffChunksDelimiters() {
		return array( /* expected array, chunk delimiter */
			array(
				array( 1, 3, 1, 4),
				'@@ -1,3 +1,4 @@'
			),
			array(
				array( 76, 17, 76, 21 ),
				'@@ -76,17 +76,21 @@'
			),
		);
	}

}
