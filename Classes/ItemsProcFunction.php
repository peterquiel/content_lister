<?php

use \TYPO3\CMS\Core\Utility\GeneralUtility; 

class Tx_ContentLister_ItemsProcFunction {

  protected function getCurrentId(){
    return substr(GeneralUtility::_GP('returnUrl'), strrpos(GeneralUtility::_GP('returnUrl') , '=') +1);
  }

  protected function getTablesFromTs(){
    $ts = t3lib_BEfunc::getPagesTSconfig($this->getCurrentId());
    if(is_array($ts)) {
      return GeneralUtility::trimExplode(',', $ts['plugin.']['contentlister.']['tables']);
    }
    return null;
  }

  protected function getExcludeColumns($config){
    $ts = t3lib_BEfunc::getPagesTSconfig($this->getCurrentId());
    if(is_array($ts)) {
      $exclude = GeneralUtility::trimExplode(',', $ts['plugin.']['contentlister.']['exclude.'][$this->getSelectedTable($config)]);
      if(is_array($exclude)){
        foreach($exclude as $key => $value){
          $exclude[$value] = 1;
        }
        return $exclude;
      }
    }
    return array();
  }

  public function getSelectedTable($config){
    $flexFormArray = GeneralUtility::xml2array($config['row']['pi_flexform']);
    if(! is_array($flexFormArray)){
      return null;
    }
    $table = $flexFormArray['data']['sDEF']['lDEF']['select_table']['vDEF'];
    if($table){
      GeneralUtility::loadTCA($table);
      return $table;
    }
    return null;
  }

  public function getTables($config){
    $tables= $this->getTablesFromTs();
    if($tables){
      $i=0;
      foreach($tables as $table){
        $config['items'][$i] = array($GLOBALS['LANG']->sL( $GLOBALS['TCA'][$table]['ctrl']['title'] ),$table);
        $i++;
      }
    }
    return $config;
  }


  public function getTableColumns($config) {
    $this->getHelpTableCol($config);
  }

  public function getSearchColumns($config){
    $this->getHelpTableCol($config);
  }

  public function getCategoryColumns($config){
    $config['items'][0] = array('','');
    return $this->getHelpTableCol($config, 1);
  }

  protected function getHelpTableCol($config, $startIndex = 0){
    $table = $this->getSelectedTable($config);
    $columns = $GLOBALS['TCA'][$table]['columns'];
    if(! is_array($columns)){
      return;
    }
    $i=$startIndex;
    $exclude = $this->getExcludeColumns($config);
    foreach($columns as $key => $value){
      if($exclude[$key]){
        continue;
      }
      $config['items'][$i] = array($GLOBALS['LANG']->sL($value['label']), $key);
      $i ++;
    }
    return $config;	
  }

  public function getCategories($config){
    $flexFormArray = GeneralUtility::xml2array($config['row']['pi_flexform']);
    if(! is_array($flexFormArray)){
      return;
    }
    $category = $flexFormArray['data']['Category']['lDEF']['category']['vDEF'];
    $config['items'] = array();
    if($category !== null && $category !== ''){
      $result = $GLOBALS['TYPO3_DB']->exec_SELECTquery( 'distinct ' . $category, $this->getSelectedTable($config), '' ,'',$category );
      if($result != null && $GLOBALS['TYPO3_DB']->sql_num_rows($result) > 0){
        for($i=0; $entry = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result); $i++){
          $config['items'][$i] = array( $entry[$category], $entry[$category] );
        }
      }
    }
    return $config;
  }
}
