<?php

// Special:Code/MediaWiki/comments
class CodeCommentsListView extends CodeRevisionListView {
	function getPager() {
		return new CodeCommentsTablePager( $this );
	}
}

// Pager for CodeCommentsListView
class CodeCommentsTablePager extends SvnTablePager {
	function isFieldSortable( $field ) {
		return $field == 'cr_timestamp';
	}

	function getDefaultSort() {
		return 'cc_timestamp';
	}

	function getQueryInfo() {
		$query = array(
			'tables' => array( 'code_comment', 'code_rev' ),
			'fields' => array_keys( $this->getFieldNames() ),
			'conds' => array( 'cc_repo_id' => $this->mRepo->getId() ),
			'join_conds' => array(
				'code_rev' => array( 'LEFT JOIN', 'cc_repo_id = cr_repo_id AND cc_rev_id = cr_id' )
			),
			'options' => array(),
		);

		if ( count( $this->mView->mPath ) ) {
			$query['tables'][] = 'code_paths';
			$query['join_conds']['code_paths'] = array( 'INNER JOIN',
				'cc_repo_id = cp_repo_id AND cc_rev_id = cp_rev_id' );
			$query['conds']['cp_path'] = $this->mView->mPath;
		}
		if ( $this->mView->mAuthor ) {
			$query['conds']['cc_user_text'] = User::newFromName( $this->mView->mAuthor )->getName();
		}

	    return $query;
	}

	function getCountQuery() {
		$query = $this->getQueryInfo();

		$query['fields'] = array( 'COUNT( DISTINCT cc_id ) AS rev_count' );
		unset( $query['options']['GROUP BY'] );
		return $query;
	}

	function getFieldNames() {
		return array(
			'cc_timestamp' => $this->msg( 'code-field-timestamp' )->text(),
			'cc_user_text' => $this->msg( 'code-field-user' )->text(),
			'cc_rev_id' => $this->msg( 'code-field-id' )->text(),
			'cr_status' => $this->msg( 'code-field-status' )->text(),
			'cr_message' => $this->msg( 'code-field-message' )->text(),
			'cc_text' => $this->msg( 'code-field-text' )->text()
		);
	}

	function formatValue( $name, $value ) {
		$linkRenderer = \MediaWiki\MediaWikiServices::getInstance()->getLinkRenderer();
		switch ( $name ) {
		case 'cc_rev_id':
			return $linkRenderer->makeLink(
				SpecialPage::getSafeTitleFor( 'Code',
					$this->mRepo->getName() . '/' . $value . '#code-comments' ),
				$value
			);
		case 'cr_status':
			return $linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'Code',
					$this->mRepo->getName() . '/status/' . $value ),
				$this->mView->statusDesc( $value )
			);
		case 'cc_user_text':
			return Linker::userLink( - 1, $value );
		case 'cr_message':
			return $this->mView->messageFragment( $value );
		case 'cc_text':
			return $this->mView->messageFragment( $value );
		case 'cc_timestamp':
			return $this->getLanguage()->timeanddate( $value, true );
		}

		throw new Exception( '$name is invalid input.' );
	}

	function getTitle() {
		return SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() . '/comments' );
	}
}
