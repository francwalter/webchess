<?php

/*
    This file is part of WebChess. http://webchess.sourceforge.net
	Copyright 2010 Jonathan Evraire, Rodrigo Flores

    WebChess is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    WebChess is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with WebChess.  If not, see <http://www.gnu.org/licenses/>.
*/

$GETTEXT_SUPPORT = false;

/* default GUI language; may be overridden by user preference */
$GUI_LANGUAGE = 'en';
if (isset($_SESSION) && isset($_SESSION['pref_language']))
{
	if ($_SESSION['pref_language'] == 'de')
		$GUI_LANGUAGE = 'de';
}

if (!function_exists('getGuiLanguage')) {
	function getGuiLanguage()
	{
		global $GUI_LANGUAGE;

		return ($GUI_LANGUAGE == 'de') ? 'de' : 'en';
	}
}

/* optional array-based translations for future use */
$WEBCHESS_TRANSLATIONS = array();
$translationFile = __DIR__ . '/locale/' . getGuiLanguage() . '.php';
if (is_file($translationFile))
{
	$loadedTranslations = require $translationFile;
	if (is_array($loadedTranslations))
		$WEBCHESS_TRANSLATIONS = $loadedTranslations;
}

if (!function_exists('webchessTranslate')) {
	function webchessTranslate($text)
	{
		global $WEBCHESS_TRANSLATIONS;

		if (isset($WEBCHESS_TRANSLATIONS[$text]))
			return $WEBCHESS_TRANSLATIONS[$text];

		return $text;
	}
}

if ( ! function_exists('gettext')) {
	function gettext($text) {
		return webchessTranslate($text);
	}
}

// Must do some testing before releasing it to mainstream
if($GETTEXT_SUPPORT) {
	/* map GUI setting to locale */
	if ($GUI_LANGUAGE == 'de')
		$LANGUAGE = 'de_DE';
	else
		$LANGUAGE = 'en_US';

	putenv("LANG=$LANGUAGE");
	setlocale(LC_ALL, $LANGUAGE);

	// Set the text domain as 'webchess'
	$domain = 'webchess';
	bindtextdomain($domain, "./locale");
	textdomain($domain);
}
