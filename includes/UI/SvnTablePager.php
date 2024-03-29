<?php

namespace MediaWiki\Extension\CodeReview\UI;

use MediaWiki\Extension\CodeReview\Backend\CodeRepository;
use stdClass;
use TablePager;

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

	/**
	 * @param string $field
	 * @return bool
	 */
	public function isFieldSortable( $field ) {
		return $field == $this->getDefaultSort();
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @param stdClass $row
	 * @return string
	 */
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
		// In case formatValue needs to know
		$this->mCurrentRow = $row;
		foreach ( $fieldNames as $field => $name ) {
			$value = $row->$field ?? null;
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

	/**
	 * @return string
	 */
	public function getStartBody() {
		$this->getOutput()->addModules( 'ext.codereview.overview' );
		return parent::getStartBody();
	}
}
