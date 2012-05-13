<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

# Include PHP5 Akismet class from http://www.achingbrain.net/stuff/akismet (GPL)
require_once( 'Akismet.class.php' );

#Extension credits
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'AkismetKlik',
	'author' => 'Carl Austin Bennett',
	'url' => 'https://www.mediawiki.org/wiki/Extension:AkismetKlik',
	'descriptionmsg' => 'akismetklik-desc',
);

$dir = dirname( __FILE__ ) . '/';
$wgExtensionMessagesFiles['AkismetKlik'] = $dir . 'AkismetKlik.i18n.php';

# Set site-specific configuration values
$wgAKkey = '';
$wgAKSiteUrl = '';

#
# MediaWiki hooks
#
# Loader for spam blacklist feature
# Include this from LocalSettings.php
$wgHooks['EditFilterMerged'][] = 'wfAkismetFilterMerged';

/**
 * Get an instance of AkismetKlik and do some first-call initialisation.
 * All actual functionality is implemented in that object
 * @return AkismetKlik
 */
function wfAkismetKlikObject() {
	global $wgSpamBlacklistSettings, $wgPreSpamFilterCallback;
	static $spamObj;
	if ( !$spamObj ) {
		$spamObj = new AkismetKlik( $wgSpamBlacklistSettings );
		$spamObj->previousFilter = $wgPreSpamFilterCallback;
	}
	return $spamObj;
}

/**
 * Hook function for $wgFilterCallback
 * @param $title Title
 * @param $text string
 * @param $section
 * @return bool
 */
function wfAkismetFilter( &$title, $text, $section ) {
	$spamObj = wfAkismetKlikObject();
	return $spamObj->filter( $title, $text, $section );
}

/**
 * Hook function for EditFilterMerged, replaces wfAkismetFilter
 * @param $editPage EditPage
 * @param $text string
 * @return bool
 */
function wfAkismetFilterMerged( $editPage, $text ) {
	$spamObj = new AkismetKlik();
	$ret = $spamObj->filter( $editPage->getArticle()->getTitle(), $text, '', $editPage );
	return !$ret;
}

/**
 * This class provides the interface to the filters
 */
class AkismetKlik {

	public $previousFilter;

	/**
	 * @param $settings array
	 */
	function __construct( $settings = array() ) {
		foreach ( $settings as $name => $value ) {
			$this->$name = $value;
		}
	}

	/**
	 * @param Title $title
	 * @param string $text Text of section, or entire text if $editPage!=false
	 * @param string $section Section number or name
	 * @param EditPage|bool $editPage EditPage if EditFilterMerged was called, false otherwise
	 * @throws MWException
	 * @return bool True if the edit should not be allowed, false otherwise
	 * If the return value is true, an error will have been sent to $wgOut
	 */
	function filter( &$title, $text, $section, $editPage = false ) {
		global $wgParser, $wgUser, $wgAKSiteUrl, $wgAKkey, $IP;

		if ( strlen( $wgAKkey ) == 0 ) {
			throw new MWException( "Set $wgAKkey" );
		}
		if ( strlen( $wgAKkey ) == 0 ) {
			throw new MWException( "Set $wgAKkey" );
		}

		wfProfileIn( __METHOD__ );

		$text = str_replace( '.', '.', $text );

		# Run parser to strip SGML comments and such out of the markup
		if ( $editPage ) {
			$editInfo = $editPage->getArticle()->prepareTextForEdit( $text );
			$out = $editInfo->output;
			$pgtitle = $title;
		} else {
			$options = new ParserOptions();
			$text = $wgParser->preSaveTransform( $text, $title, $wgUser, $options );
			$out = $wgParser->parse( $text, $title, $options );
			$pgtitle = "";
		}
		$links = implode( "\n", array_keys( $out->getExternalLinks() ) );

		# Do the match
		if ( $wgUser->mName == "" ) {
			$user = $IP;
		} else {
			$user = $wgUser->mName;
		}
		$akismet = new Akismet( $wgAKSiteUrl, $wgAKkey );
		$akismet->setCommentAuthor( $user );
		$akismet->setCommentAuthorEmail( $wgUser->mEmail );
		$akismet->setCommentAuthorURL( $links );
		$akismet->setCommentContent( $text );
		$akismet->setCommentType( "wiki" );
		$akismet->setPermalink( $wgAKSiteUrl . '/wiki/' . $pgtitle );
		if ( $akismet->isCommentSpam() && !$wgUser->isAllowed( 'bypassakismet' ) ) {
			wfDebugLog( 'AkismetKlik', "Match!\n" );
			EditPage::spamPage( "http://akismet.com blacklist error" );
			wfProfileOut( __METHOD__ );
			return true;
		}
		wfProfileOut( __METHOD__ );
		return false;
	}
}
