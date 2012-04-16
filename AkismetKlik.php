<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

#
# Include PHP5 Akismet class from http://www.achingbrain.net/stuff/akismet (GPL)
#
require_once('Akismet.class.php');

#Extension credits
$wgExtensionCredits['other'][] = array(
	'name' => 'AkismetKlik',
	'author' => 'Carl Austin Bennett',
	'url' => 'http://www.mediawiki.org/wiki/Extension:AkismetKlik',
	'description' => 'Rejects edits from suspected comment spammers on Akismet\'s blacklist.',
);

# Set site-specific configuration values
#$wgAKkey='867-5309';
#$siteURL='http://wiki.example.org';

#
# MediaWiki hooks
#
# Loader for spam blacklist feature
# Include this from LocalSettings.php

global $wgAkismetFilterCallback, $wgPreAkismetFilterCallback, $wgUser;
$wgPreAkismetFilterCallback = false;

if ( defined( 'MW_SUPPORTS_EDITFILTERMERGED' ) ) {
	$wgHooks['EditFilterMerged'][] = 'wfAkismetFilterMerged';
} else {
	if ( $wgFilterCallback ) {
		$wgPreAkismetFilterCallback = $wgFilterCallback;
	}
	$wgFilterCallback = 'wfAkismetFilter';
}

#$wgHooks['EditFilter'][] = 'wfAkismetFilter';

/**
 * Get an instance of AkismetKlik and do some first-call initialisation.
 * All actual functionality is implemented in that object
 */
function wfAkismetKlikObject() {
	global $wgSpamBlacklistSettings, $wgPreSpamFilterCallback;
	static $spamObj;
	if ( !$spamObj ) {
		$spamObj = new AkismetKlik ( $wgSpamBlacklistSettings );
		$spamObj->previousFilter = $wgPreSpamFilterCallback;
	}
	return $spamObj;
}

/**
 * Hook function for $wgFilterCallback
 */
function wfAkismetFilter( &$title, $text, $section ) {
	$spamObj = wfAkismetKlikObject();
	return $spamObj->filter( $title, $text, $section );
}

/**
 * Hook function for EditFilterMerged, replaces wfAkismetFilter
 */
function wfAkismetFilterMerged( $editPage, $text ) {
	$spamObj = new AkismetKlik();
	$ret = $spamObj->filter( $editPage->mArticle->getTitle(), $text, '', $editPage );
	// Return convention for hooks is the inverse of $wgAkismetFilterCallback
	return !$ret;
}

#
# This class provides the interface to the filters
#
class AkismetKlik {

	function AkismetKlik( $settings = array() ) {
		foreach ( $settings as $name => $value ) {
			$this->$name = $value;
			echo $value;
		}
	}

	/**
	 * @param Title $title
	 * @param string $text Text of section, or entire text if $editPage!=false
	 * @param string $section Section number or name
	 * @param EditPage $editPage EditPage if EditFilterMerged was called, false otherwise
	 * @return True if the edit should not be allowed, false otherwise
	 * If the return value is true, an error will have been sent to $wgOut
	 */
	function filter( &$title, $text, $section, $editPage = false ) {
		global $wgArticle, $wgVersion, $wgOut, $wgParser, $wgUser;
		global $siteURL, $wgAKkey;

		$fname = 'wfAkismetKlikFilter';
		wfProfileIn( $fname );

		# Call the rest of the hook chain first
		if ( $this->previousFilter ) {
			$f = $this->previousFilter;
			if ( $f( $title, $text, $section ) ) {
				wfProfileOut( $fname );
				return true;
			}
		}

		$this->title = $title;
		$this->text = $text;
		$this->section = $section;
		$text = str_replace( '.', '.', $text );

		# Run parser to strip SGML comments and such out of the markup
		if ( $editPage ) {
			$editInfo = $editPage->mArticle->prepareTextForEdit( $text );
			$out = $editInfo->output;
			$pgtitle = $title;
		} else {
			$options = new ParserOptions();
			$text = $wgParser->preSaveTransform( $text, $title, $wgUser, $options );
			$out = $wgParser->parse( $text, $title, $options );
			$pgtitle = "";
		}
		$links = implode( "\n", array_keys( $out->getExternalLinks()));

		# Do the match
		if ($wgUser->mName == "") $user = $IP;
		else $user = $wgUser->mName;
		$akismet = new Akismet($siteURL, $wgAKkey);
		$akismet->setCommentAuthor($user);
		$akismet->setCommentAuthorEmail($wgUser->mEmail);
		$akismet->setCommentAuthorURL($links);
		$akismet->setCommentContent($text);
		$akismet->setCommentType("wiki");
		$akismet->setPermalink($siteURL . '/wiki/' . $pgtitle);
		if($akismet->isCommentSpam()&&!$wgUser->isAllowed( 'bypassakismet' ))
		{
			wfDebugLog( 'AkismetKlik', "Match!\n" );
			if ( $editPage ) {
				$editPage->spamPage( "http://akismet.com blacklist error" );
			} else {
				EditPage::spamPage( "http://akismet.com blacklist error" );
			}
			return true;
		}
		return false;
	}
}
