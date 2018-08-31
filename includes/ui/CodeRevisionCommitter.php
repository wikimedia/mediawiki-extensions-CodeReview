<?php

class CodeRevisionCommitter extends CodeRevisionView {
	function execute() {
		global $wgRequest, $wgOut, $wgUser;

		if ( !$wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) ) {
			$wgOut->addHTML( '<strong>' . wfMessage( 'sessionfailure' )->escaped() . '</strong>' );
			parent::execute();
			return;
		}
		if ( !$this->mRev ) {
			parent::execute();
			return;
		}

		$commentId = $this->revisionUpdate(
			$this->mStatus,
			$this->mAddTags,
			$this->mRemoveTags,
			$this->mSignoffFlags,
			$this->mStrikeSignoffs,
			$this->mAddReferences,
			$this->mRemoveReferences,
			$this->text,
			$wgRequest->getIntOrNull( 'wpParent' ),
			$this->mAddReferenced,
			$this->mRemoveReferenced
		);

		$redirTarget = null;

		// For comments, take us back to the rev page focused on the new comment
		if ( $commentId !== 0 && !$this->jumpToNext ) {
			$redirTarget = $this->commentLink( $commentId );
		}

		// Return to rev page
		if ( !$redirTarget ) {
			// Was "next" (or "save & next") clicked?
			if ( $this->jumpToNext ) {
				$next = $this->mRev->getNextUnresolved( $this->mPath );
				if ( $next ) {
					$redirTarget = SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() . '/' . $next );
				} else {
					$redirTarget = SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() );
				}
			} else {
				# $redirTarget already set for comments
				$redirTarget = $this->revLink();
			}
		}
		$wgOut->redirect( $redirTarget->getFullUrl( [ 'path' => $this->mPath ] ) );
	}

	/**
	 * Does the revision database update
	 *
	 * @param string $status Status to set the revision to
	 * @param array $addTags Tags to add to the revision
	 * @param array $removeTags Tags to remove from the Revision
	 * @param array $addSignoffs Sign-off flags to add
	 * @param array $strikeSignoffs Sign-off IDs to strike
	 * @param array $addReferences Revision IDs to add reference from
	 * @param array $removeReferences Revision IDs to remove references from
	 * @param string $commentText Comment to add to the revision
	 * @param null|int $parent What the parent comment is (if a subcomment)
	 * @param array $addReferenced
	 * @param array $removeReferenced
	 * @return int Comment ID if added, else 0
	 */
	public function revisionUpdate( $status, $addTags, $removeTags, $addSignoffs, $strikeSignoffs,
						$addReferences, $removeReferences, $commentText,
						$parent = null, $addReferenced, $removeReferenced
					) {
		if ( !$this->mRev ) {
			return false;
		}

		global $wgUser;

		$dbw = wfGetDB( DB_MASTER );
		$dbw->startAtomic( __METHOD__ );

		// Change the status if allowed
		$statusChanged = false;
		if ( $this->mRev->isValidStatus( $status ) && $this->validPost( 'codereview-set-status' ) ) {
			$statusChanged = $this->mRev->setStatus( $status, $wgUser );
		}
		$validAddTags = $validRemoveTags = [];
		if ( count( $addTags ) && $this->validPost( 'codereview-add-tag' ) ) {
			$validAddTags = $addTags;
		}
		if ( count( $removeTags ) && $this->validPost( 'codereview-remove-tag' ) ) {
			$validRemoveTags = $removeTags;
		}
		// If allowed to change any tags, then do so
		if ( count( $validAddTags ) || count( $validRemoveTags ) ) {
			$this->mRev->changeTags( $validAddTags, $validRemoveTags, $wgUser );
		}
		// Add any signoffs
		if ( count( $addSignoffs ) && $this->validPost( 'codereview-signoff' ) ) {
			$this->mRev->addSignoff( $wgUser, $addSignoffs );
		}
		// Strike any signoffs
		if ( count( $strikeSignoffs ) && $this->validPost( 'codereview-signoff' ) ) {
			$this->mRev->strikeSignoffs( $wgUser, $strikeSignoffs );
		}
		// Add reference if requested
		if ( count( $addReferences ) && $this->validPost( 'codereview-associate' ) ) {
			$this->mRev->addReferencesFrom( $addReferences );
		}
		// Remove references if requested
		if ( count( $removeReferences ) && $this->validPost( 'codereview-associate' ) ) {
			$this->mRev->removeReferencesFrom( $removeReferences );
		}
		// Add reference if requested
		if ( count( $addReferenced ) && $this->validPost( 'codereview-associate' ) ) {
			$this->mRev->addReferencesTo( $addReferenced );
		}
		// Remove references if requested
		if ( count( $removeReferenced ) && $this->validPost( 'codereview-associate' ) ) {
			$this->mRev->removeReferencesTo( $removeReferenced );
		}

		// Add any comments
		$commentAdded = false;
		$commentId = 0;
		if ( strlen( $commentText ) && $this->validPost( 'codereview-post-comment' ) ) {
			// $isPreview = $wgRequest->getCheck( 'wpPreview' );
			$commentId = $this->mRev->saveComment( $commentText, $parent );

			$commentAdded = ( $commentId !== 0 );
		}

		$dbw->endAtomic( __METHOD__ );

		if ( $statusChanged || $commentAdded ) {
			$url = $this->mRev->getCanonicalUrl( $commentId );
			if ( $statusChanged && $commentAdded ) {
				$this->mRev->emailNotifyUsersOfChanges( 'codereview-email-subj4', 'codereview-email-body4',
					$wgUser->getName(), $this->mRev->getIdStringUnique(), $this->mRev->getOldStatus(),
					$this->mRev->getStatus(), $url, $this->text, $this->mRev->getMessage()
				);
			} elseif ( $statusChanged ) {
				$this->mRev->emailNotifyUsersOfChanges( 'codereview-email-subj3', 'codereview-email-body3',
					$wgUser->getName(), $this->mRev->getIdStringUnique(), $this->mRev->getOldStatus(),
					$this->mRev->getStatus(), $url, $this->mRev->getMessage()
				);
			} elseif ( $commentAdded ) {
				$this->mRev->emailNotifyUsersOfChanges( 'codereview-email-subj', 'codereview-email-body',
					$wgUser->getName(), $url, $this->mRev->getIdStringUnique(), $this->text,
					$this->mRev->getMessage()
				);
			}
		}

		return $commentId;
	}
}