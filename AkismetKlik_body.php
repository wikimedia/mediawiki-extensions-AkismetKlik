<?php
/**
 * This class provides the interface to the filters
 */
class AkismetKlik {
	public $previousFilter;

	/**
	 * @param array $settings
	 */
	function __construct( $settings = [] ) {
		foreach ( $settings as $name => $value ) {
			$this->$name = $value;
		}
	}

	/**
	 * Hook function for EditFilterMergedContent, replaces wfAkismetFilter
	 * @param IContextSource $context
	 * @param Content $content
	 * @return bool
	 */
	public static function onAkismetFilterMergedContent( $context, $content ) {
		$spamObj = new AkismetKlik();
		$ret = $spamObj->filter( $context->getTitle(), $content, '', $context->getWikiPage() );

		return !$ret;
	}

	/**
	 * @param Title $title
	 * @param Content $content Content of section
	 * @param string $section Section number or name
	 * @param WikiPage $wikiPage WikiPage passed from EditFilterMergedContent
	 * @throws MWException
	 * @return bool True if the edit should not be allowed, false otherwise
	 * If the return value is true, an error will have been sent to $wgOut
	 */
	function filter( $title, $content, $section, $wikiPage ) {
		global $wgUser, $wgAKSiteUrl, $wgAKkey;
		// @codingStandardsIgnoreStart
		global $IP;
		// @codingStandardsIgnoreEnd

		if ( strlen( $wgAKkey ) == 0 ) {
			throw new MWException( 'Set $wgAKkey in LocalSettings.php or relevant configuration file.' );
		}

		# Run parser to strip SGML comments and such out of the markup
		$text = ContentHandler::getContentText( $content );
		$editInfo = $wikiPage->prepareContentForEdit( $content );
		$out = $editInfo->output;
		$links = implode( "\n", array_keys( $out->getExternalLinks() ) );

		# Do the match
		if ( $wgUser->mName == '' ) {
			$user = $IP;
		} else {
			$user = $wgUser->mName;
		}
		$akismet = new Akismet( $wgAKSiteUrl, $wgAKkey );
		$akismet->setCommentAuthor( $user );
		$akismet->setCommentAuthorEmail( $wgUser->getEmail() );
		$akismet->setCommentAuthorURL( $links );
		$akismet->setCommentContent( $text );
		$akismet->setCommentType( 'wiki' );
		$akismet->setPermalink( $wgAKSiteUrl . '/wiki/' . $title );
		if ( $akismet->isCommentSpam() && !$wgUser->isAllowed( 'bypassakismet' ) ) {
			wfDebugLog( 'AkismetKlik', "Match!\n" );
			$editInfo->spamPageWithContent( 'http://akismet.com blacklist error' );

			return true;
		}

		return false;
	}
}
