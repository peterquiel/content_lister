<?php
/*created by Peter Quiel peter.quiel@gmail.com at 2016-04-28.*/
if (!defined ('TYPO3_MODE')) die ('Access denied.');

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin( $_EXTKEY, 'ContentList', array('List' => 'list,detail,category,search'));

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:' . $_EXTKEY . '/Configuration/TypoScript/pageTs.txt">');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, '/Configuration/TypoScript', 'ContentLister');
?>
