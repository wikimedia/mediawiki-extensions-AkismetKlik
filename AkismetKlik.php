<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

# Include PHP5 Akismet class from http://www.achingbrain.net/stuff/akismet (GPL)
require_once 'Akismet.class.php';

# Extension credits
$wgExtensionCredits['other'][] = [
	'path' => __FILE__,
	'name' => 'AkismetKlik',
	'author' => 'Carl Austin Bennett',
	'url' => 'https://www.mediawiki.org/wiki/Extension:AkismetKlik',
	'descriptionmsg' => 'akismetklik-desc',
];

$wgMessagesDirs['AkismetKlik'] = __DIR__ . '/i18n';

# Set site-specific configuration values
$wgAKkey = '';
$wgAKSiteUrl = '';

# MediaWiki hooks

# Loader for spam blacklist feature
# Include this from LocalSettings.php
$wgHooks['EditFilterMergedContent'][] = 'wfAkismetFilterMergedContent';

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
 * Hook function for EditFilterMergedContent, replaces wfAkismetFilter
 * @param $context IContextSource
 * @param $content Content
 * @param $status Status
 * @param $summary string
 * @param $user User
 * @param $minoredit bool
 * @return bool
 */
function wfAkismetFilterMergedContent( $context, $content, $status, $summary, $user, $minoredit ) {
	$spamObj = new AkismetKlik();
	$ret = $spamObj->filter( $context->getTitle(), $content, '', $context->getWikiPage() );

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
	function __construct( $settings = [] ) {
		foreach ( $settings as $name => $value ) {
			$this->$name = $value;
		}
	}

	/**
	 * @param Title $title
	 * @param Content $content Content of section
	 * @param string $section Section number or name
	 * @param WikiPage $editPage WikiPage passed from EditFilterMergedContent
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

		wfProfileIn( __METHOD__ );

		# Run parser to strip SGML comments and such out of the markup
		$text = ContentHandler::getContentText( $content );
		$editInfo = $wikiPage->prepareContentForEdit( $content );
		$out = $editInfo->output;
		$pgtitle = $title;
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
		$akismet->setPermalink( $wgAKSiteUrl . '/wiki/' . $pgtitle );
		if ( $akismet->isCommentSpam() && !$wgUser->isAllowed( 'bypassakismet' ) ) {
			wfDebugLog( 'AkismetKlik', "Match!\n" );
			$editPage->spamPageWithContent( 'http://akismet.com blacklist error' );
			wfProfileOut( __METHOD__ );

			return true;
		}
		wfProfileOut( __METHOD__ );

		return false;
	}
}
