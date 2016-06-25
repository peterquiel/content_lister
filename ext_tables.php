<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin($_EXTKEY, "ContentList", "The Content Lister");

//include_once(t3lib_extMgm::extPath($_EXTKEY).'class.tx_qz_list_itemsProcFunc.php');

t3lib_div::loadTCA('tt_content');


//t3lib_extMgm::addPlugin(array('LLL:EXT:qz_contentlister/locallang.xml:tt_content.list_type_pi1', $_EXTKEY.'_pi1'),'list_type');


t3lib_extMgm::addStaticFile($_EXTKEY,'static/Content_Lister/', 'Content Lister');

$extensionName = \TYPO3\CMS\Core\Utility\GeneralUtility::underscoredToUpperCamelCase($_EXTKEY);
$frontendpluginName = 'ContentList'; //Your Front-end Plugin Name
$pluginSignature = strtolower($extensionName) . '_'.strtolower($frontendpluginName);
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature] = 'pi_flexform';
$GLOBALS['TCA']['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignatur]='layout,select_key,pages,recursive';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue($pluginSignature, 'FILE:EXT:' . $_EXTKEY . '/Configuration/FlexForms/list.xml');
?>
