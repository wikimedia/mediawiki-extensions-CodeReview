<?php

// Special:Code/MediaWiki
class CodeStatusChangeListView extends CodeRevisionListView {
	function getPager() {
		return new CodeStatusChangeTablePager( $this );
	}

	function getRevCount( $dbr ) {
		return -1;
	}
}

// Pager for CodeRevisionListView
class CodeStatusChangeTablePager extends SvnTablePager {

	function isFieldSortable( $field ) {
		return $field == 'cpc_timestamp';
	}

	function getDefaultSort() {
		return 'cpc_timestamp';
	}

	function getQueryInfo() {
		$query = array(
			'tables' => array( 'code_prop_changes', 'code_rev' ),
			'fields' => array_keys( $this->getFieldNames() ),
			'conds' => array( 'cpc_repo_id' => $this->mRepo->getId(), 'cpc_attrib' => 'status' ),
			'join_conds' => array(
				'code_rev' => array( 'LEFT JOIN', 'cpc_repo_id = cr_repo_id AND cpc_rev_id = cr_id' )
			),
			'options' => array(),
		);

		if( count( $this->mView->mPath ) ) {
			$query['tables'][] = 'code_paths';
			$query['join_conds']['code_paths'] = array( 'INNER JOIN', 'cpc_repo_id = cp_repo_id AND cpc_rev_id = cp_rev_id' );
			$query['conds']['cp_path'] = $this->mView->mPath;
		}
		if ( $this->mView->mAuthor ) {
			$query['conds']['cpc_user_text'] = User::newFromName( $this->mView->mAuthor )->getName();
		}

		return $query;
	}

	function getFieldNames() {
		return array(
			'cpc_timestamp' => $this->msg( 'code-field-timestamp' )->text(),
			'cpc_user_text' => $this->msg( 'code-field-user' )->text(),
			'cpc_rev_id' => $this->msg( 'code-field-id' )->text(),
			'cr_author' => $this->msg( 'code-field-author' )->text(),
			'cr_message' => $this->msg( 'code-field-message' )->text(),
			'cpc_removed' => $this->msg( 'code-old-status' )->text(),
			'cpc_added' => $this->msg( 'code-new-status' )->text(),
			'cr_status' => $this->msg( 'code-field-status' )->text(),
		);
	}

	function formatValue( $name, $value ) {
		// Give grep a chance to find the usages:
		// code-status-new, code-status-fixme, code-status-reverted, code-status-resolved,
		// code-status-ok, code-status-deferred, code-status-old
		switch( $name ) {
		case 'cpc_rev_id':
			return Linker::link(
				SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() . '/' . $value . '#code-changes' ),
				htmlspecialchars( $value ) );
		case 'cr_author':
			return $this->mView->authorLink( $value );
		case 'cr_message':
			return $this->mView->messageFragment( $value );
		case 'cr_status':
			return Linker::link(
				SpecialPage::getTitleFor( 'Code',
					$this->mRepo->getName() . '/status/' . $value ),
				htmlspecialchars( $this->mView->statusDesc( $value ) ) );
		case 'cpc_user_text':
			return Linker::userLink( - 1, $value );
		case 'cpc_removed':
			return $this->msg( $value ? "code-status-$value" : "code-status-new" )->escaped();
		case 'cpc_added':
			return $this->msg( "code-status-$value" )->escaped();
		case 'cpc_timestamp':
			return $this->getLanguage()->timeanddate( $value, true );
		}

		throw new MWException( '$name is invalid input.');
	}

	function getTitle() {
		return SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() . '/statuschanges' );
	}
}
