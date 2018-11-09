<?php

/**
 * Extended by CodeRevisionListView and CodeRevisionView
 */
abstract class CodeView {
	/**
	 * @var CodeRepository
	 */
	public $mRepo;

	/**
	 * @var CodeCommentLinkerHtml
	 */
	public $codeCommentLinkerHtml;

	/**
	 * @var CodeCommentLinkerWiki
	 */
	public $codeCommentLinkerWiki;

	/**
	 * @var string
	 */
	public $mPath;

	/**
	 * @var string
	 */
	public $mAuthor;

	/**
	 * @var string
	 */
	public $mStatus;

	public function __construct( $repo ) {
		$this->mRepo = ( $repo instanceof CodeRepository )
			? $repo
			: CodeRepository::newFromName( $repo );

		$this->codeCommentLinkerHtml = new CodeCommentLinkerHtml( $this->mRepo );
		$this->codeCommentLinkerWiki = new CodeCommentLinkerWiki( $this->mRepo );
	}

	public function validPost( $permission ) {
		global $wgRequest, $wgUser;
		return $wgRequest->wasPosted()
			&& $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) )
			&& $wgUser->isAllowed( $permission );
	}

	abstract public function execute();

	public function authorLink( $author, $extraParams = [] ) {
		$repo = $this->mRepo->getName();
		$special = SpecialPage::getTitleFor( 'Code', "$repo/author/$author" );
		$linkRenderer = \MediaWiki\MediaWikiServices::getInstance()->getLinkRenderer();
		return $linkRenderer->makeLink( $special, $author, [], $extraParams );
	}

	public function statusDesc( $status ) {
		return wfMessage( "code-status-$status" )->text();
	}

	public function formatMessage( $text ) {
		$text = nl2br( htmlspecialchars( $text ) );
		return $this->codeCommentLinkerHtml->link( $text );
	}

	public function messageFragment( $value ) {
		global $wgLang;
		$message = trim( $value );
		$lines = explode( "\n", $message, 2 );
		$first = $lines[0];

		$html = $this->formatMessage( $first );
		$truncated = $wgLang->truncateHtml( $html, 80 );

		if ( count( $lines ) > 1 ) { // If multiline, we might want to add an ellipse
			$ellipsis = wfMessage( 'ellipsis' )->text();
			// Hack: don't add if the end is already an ellipse
			if ( substr( $truncated, -strlen( $ellipsis ) ) !== $ellipsis ) {
				$truncated .= $ellipsis;
			}
		}

		return $truncated;
	}

	/**
	 * Formatted HTML array for properties display
	 * @param array $fields 'propname' => HTML data
	 * @return string
	 */
	public function formatMetaData( $fields ) {
		$html = '<table class="mw-codereview-meta">';
		foreach ( $fields as $label => $data ) {
			$html .= "<tr><td>" . wfMessage( $label )->escaped() . "</td><td>$data</td></tr>\n";
		}
		return $html . "</table>\n";
	}

	/**
	 * @return bool|CodeRepository
	 */
	public function getRepo() {
		if ( $this->mRepo ) {
			return $this->mRepo;
		}
		return false;
	}
}

abstract class SvnTablePager extends TablePager {
	/**
	 * @var CodeRepository
	 */
	protected $mRepo;

	/**
	 * @var CodeView
	 */
	protected $mView;

	/**
	 * @param CodeView $view
	 */
	public function __construct( $view ) {
		$this->mView = $view;
		$this->mRepo = $view->mRepo;
		$this->mDefaultDirection = true;
		parent::__construct();
	}

	public function isFieldSortable( $field ) {
		return $field == $this->getDefaultSort();
	}

	public function formatRevValue( $name, $value, $row ) {
		return $this->formatValue( $name, $value );
	}

	/**
	 * @note this function is poorly factored in the parent class
	 * @param stdClass $row
	 * @return string
	 */
	public function formatRow( $row ) {
		$css = "mw-codereview-status-{$row->cr_status}";
		$s = "<tr class=\"$css\">\n";
		// Some of this stolen from Pager.php...sigh
		$fieldNames = $this->getFieldNames();
		$this->mCurrentRow = $row; # In case formatValue needs to know
		foreach ( $fieldNames as $field => $name ) {
			$value = isset( $row->$field ) ? $row->$field : null;
			$formatted = strval( $this->formatRevValue( $field, $value, $row ) );
			if ( $formatted == '' ) {
				$formatted = '&#160;';
			}
			$class = 'TablePager_col_' . htmlspecialchars( $field );
			$s .= "<td class=\"$class\">$formatted</td>\n";
		}
		$s .= "</tr>\n";
		return $s;
	}

	public function getStartBody() {
		$this->getOutput()->addModules( 'ext.codereview.overview' );
		return parent::getStartBody();
	}
}
