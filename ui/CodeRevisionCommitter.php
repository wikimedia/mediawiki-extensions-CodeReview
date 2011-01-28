<?php

class CodeRevisionCommitter extends CodeRevisionView {
	function execute() {
		global $wgRequest, $wgOut, $wgUser;

		if ( !$wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) ) {
			$wgOut->addHTML( '<strong>' . wfMsg( 'sessionfailure' ) . '</strong>' );
			parent::execute();
			return;
		}
		if ( !$this->rev ) {
			parent::execute();
			return;
		}

		$commentId = $this->revisionUpdate( $this->status, $this->addTags, $this->removeTags,
			$this->signoffFlags, $this->strikeSignoffs, $this->addReference, $this->removeReferences,
			$this->text, $wgRequest->getIntOrNull( 'wpParent' ),
			$wgRequest->getInt( 'wpReview' )
		);

		$redirTarget = null;

		// For comments, take us back to the rev page focused on the new comment
		if ( $commentId !== 0 && !$this->jumpToNext ) {
			$redirTarget = $this->commentLink( $commentId );
		}

		// Return to rev page
		if ( !$redirTarget ) {
			// Was "next & unresolved" clicked?
			if ( $this->jumpToNext ) {
				$next = $this->rev->getNextUnresolved( $this->path );
				if ( $next ) {
					$redirTarget = SpecialPage::getTitleFor( 'Code', $this->repo->getName() . '/' . $next );
				} else {
					$redirTarget = SpecialPage::getTitleFor( 'Code', $this->repo->getName() );
				}
			} else {
				# $redirTarget already set for comments
				$redirTarget = $this->revLink();
			}
		}
		$wgOut->redirect( $redirTarget->getFullUrl( array( 'path' => $this->path ) ) );
	}

	/**
	 * Does the revision database update
	 *
	 * @param string $status Status to set the revision to
	 * @param Array $addTags Tags to add to the revision
	 * @param Array $removeTags Tags to remove from the Revision
	 * @param Array $addSignoffs Array of sign-off flags to add
	 * @param Array $strikeSignoffs Array of sign-off IDs to strike
	 * @param Array $addReferences Array of revision IDs to add reference from
	 * @param Array $removeReferences Array of revision IDs to remove references from
	 * @param string $commentText Comment to add to the revision
	 * @param null|int $parent What the parent comment is (if a subcomment)
	 * @param int $review (unused)
	 * @return int Comment ID if added, else 0
	 */
	public function revisionUpdate( $status, $addTags, $removeTags, $addSignoffs, $strikeSignoffs,
						$addReferences, $removeReferences, $commentText,
						$parent = null, $review = 0 ) {
		if ( !$this->rev ) {
			return false;
		}

		global $wgUser;

		$dbw = wfGetDB( DB_MASTER );

		$dbw->begin();
		// Change the status if allowed
		$statusChanged = false;
		if ( $this->rev->isValidStatus( $status ) && $this->validPost( 'codereview-set-status' ) ) {
			$statusChanged = $this->rev->setStatus( $status, $wgUser );
		}
		$validAddTags = $validRemoveTags = array();
		if ( count( $addTags ) && $this->validPost( 'codereview-add-tag' ) ) {
			$validAddTags = $addTags;
		}
		if ( count( $removeTags ) && $this->validPost( 'codereview-remove-tag' ) ) {
			$validRemoveTags = $removeTags;
		}
		// If allowed to change any tags, then do so
		if ( count( $validAddTags ) || count( $validRemoveTags ) ) {
			$this->rev->changeTags( $validAddTags, $validRemoveTags, $wgUser );
		}
		// Add any signoffs
		if ( count( $addSignoffs ) && $this->validPost( 'codereview-signoff' ) )  {
			$this->rev->addSignoff( $wgUser, $addSignoffs );
		}
		// Strike any signoffs
		if ( count( $strikeSignoffs ) && $this->validPost( 'codereview-signoff' ) ) {
			$this->rev->strikeSignoffs( $wgUser, $strikeSignoffs );
		}
		// Add reference if requested
		if ( count( $addReferences ) && $this->validPost( 'codereview-associate' ) ) {
			$this->rev->addReferencesFrom( $addReferences );
		}
		// Remove references if requested
		if ( count( $removeReferences ) && $this->validPost( 'codereview-associate' ) ) {
			$this->rev->removeReferencesFrom( $removeReferences );
		}

		// Add any comments
		$commentAdded = false;
		$commentId = 0;
		if ( strlen( $commentText ) && $this->validPost( 'codereview-post-comment' ) ) {
			// $isPreview = $wgRequest->getCheck( 'wpPreview' );
			$commentId = $this->rev->saveComment( $commentText, $review, $parent );

			$commentAdded = ($commentId !== 0);
		}
		$dbw->commit();

		if ( $statusChanged || $commentAdded ) {
			if ( $statusChanged && $commentAdded ) {
				$url = $this->rev->getFullUrl( $commentId );
				$this->rev->emailNotifyUsersOfChanges( 'codereview-email-subj4', 'codereview-email-body4',
					$wgUser->getName(), $this->rev->getIdStringUnique(), $this->rev->getOldStatus(), $this->rev->getStatus(),
					$url, $this->text
				);
			} else if ( $statusChanged ) {
				$this->rev->emailNotifyUsersOfChanges( 'codereview-email-subj3', 'codereview-email-body3',
					$wgUser->getName(), $this->rev->getIdStringUnique(), $this->rev->getOldStatus(), $this->rev->getStatus()
				);
			} else if ( $commentAdded ) {
				$url = $this->rev->getFullUrl( $commentId );
				$this->rev->emailNotifyUsersOfChanges( 'codereview-email-subj', 'codereview-email-body',
					$wgUser->getName(), $url, $this->rev->getIdStringUnique(), $this->text
				);
			}
		}

		return $commentId;
	}
}
