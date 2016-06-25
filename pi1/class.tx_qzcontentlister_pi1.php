<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2008 Armin Zimmer, Peter Quiel <peter.quiel@pq-solutions.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

require_once(PATH_tslib.'class.tslib_pibase.php');


/**
 * Plugin 'Company List' for the 'qz_companylist' extension.
 *
 * @author	Armin Zimmer, Peter Quiel <peter.quiel@pq-solutions.de>
 * @package	TYPO3
 * @subpackage	tx_qzcompanylist
 */

class tx_qzcontentlister_pi1 extends tslib_pibase {

  public $prefixId      = 'tx_qzcontentlister';		// Same as class name
  public $scriptRelPath = 'pi1/class.tx_qzcontentlister_pi1.php';	// Path to this script relative to the extension dir.
  public $extKey        = 'qz_contentlister';	// The extension key.
  public $uploadDir = 'uploads/tx_qzcontentlister/';

  protected $markerCache = array();
  /* Es gibt zwei verschiedene Listeneinträge im Template und zwar LISTENTRY_1 und LISTENTRY_2*/
  protected $numDiffEntries = 2;
  /* Das gleich wie oben nur für Kategorie einträge*/
  protected $numDiffCategoryEntries = 2;
  /* template datei */
  protected $templateSource = null;

  /* der teil des templates, welcher für den Seiten-Browser zuständig ist*/
  protected $paginatorSubpart = null;

  /* Anzahl der Ergebnisse */
  protected $resultSize = null;

  /*result cache */
  protected $entryResult = null;


  /**
   * The main method of the PlugIn
   *
   * @param	string		$content: The PlugIn content
   * @param	array		$conf: The PlugIn configuration
   * @return	The content that is displayed on the website
   */
  public function main($content,$conf)	{

    $this->conf=$conf;

    $this->pi_setPiVarDefaults();
    $this->pi_loadLL();
    $this->pi_USER_INT_obj=1;

    $this->pi_initPIflexForm();

    return $this->pi_wrapInBaseClass($content);
  }

  /* erstellt die Listenansicht */
  protected function createList(){
    return $this->createEntryList($this->fetchEntryList());
  }

  /* erstellt die Detailansicht*/ 
  protected function createDetail(){
    $entry = $this->fetchDetailEntry();
    if($entry != null){
      return $this->cObj->substituteMarkerArray($this->getDetailSubpart() ,$this->createMarkerArray($entry, false));
    }
    return null;
  }

  /* erstellt die Listenansicht für das MysqlErgebnis $result */
  protected function createEntryList($result){
    if($result != null){
      $listContent = '';
      for($i=0; $entry = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result); $i++){
        $listContent .= $this->getListEntry($entry, $i);
      }
      $out = $this->getList($listContent);
      return $this->cObj->substituteMarker($out, '###PAGINATOR###', (($this->getShowPaginator() ) ? $this->getPaginator() : '' ));
    } else { 
      return $this->getSubpart('###NORESULT###');
    }
  }

  /* erstellt den seiten-browser */
  protected function getPaginator(){
    $out = $this->getPaginatorSubpart();
    if(! $out ){
      return '';
    }
    $out = $this->cObj->substituteMarker($out, '###RESULTNOTE###', $this->getResultNote() );
    return $this->cObj->substituteSubpart($out,	'###PAGINATORCONTENT###', 	$this->getPaginatorEntries() );	
  }

  /* erstellt die kategorieübersicht */
  protected function createCategory(){
    if(! $this->getCategory() ){
      return 'No Category was specified in the plugin configuration. Hence no list can be shown.';
    }
    $result = $this->fetchCategoryList();
    if($result != null){
      $categoryContent = '';

      for($i=0; $category = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result); $i++){
        $categoryContent .= $this->getCategoryEntry($category, $i);
      }
      return $this->getCategories($categoryContent);
    }
    return null;
  }

  /* erstellt die einzelne seiten-browser einträge wie z.b next, prev und einzelnen Seiten */
  protected function getPaginatorEntries(){
    $content = $this->getPaginatorFirst();
    $content .= $this->getPaginatorPrev();

    /* mit dieser kalkulation wird bestimmt, mit welcher Seitennummer angefangen und aufgehört wird.*/
    if( $this->getEndPage() < $this->getPaginatorSize()){
      $start = 1;
      $end = $this->getEndPage();
    } else {
      $start = $this->getPage() - floor($this->getPaginatorSize() / 2);
      $end = $this->getPage() + ceil($this->getPaginatorSize() / 2);
      if($start < 1){
        $end -= $start;
        $start = 1;
      } else if ($end > $this->getEndPage() ){
        $start -= $end - $this->getEndPage();
        $end = $this->getEndPage();
      }
    }
    $linkSubpart = $this->cObj->getSubpart($this->getPaginatorSubpart(), '###PAGINATORELEMENT###');
    for($i = $start; $i <= $end; $i++ ){
      $content .= $this->cObj->substituteMarker( $this->generatePaginatorLink($linkSubpart, $i) , '###NUMBER###', $i ); 
    }		
    $content .=	$this->getPaginatorNext();
    $content .= $this->getPaginatorLast();
    return $content;
  }


  protected function getResultNote(){
    return 'Es wird Ergebnis ' . (($this->getPage() - 1) * $this->getPageSize() + 1 ) . ' bis ' .
      ( (($this->getPage() * $this->getPageSize() ) < $this->resultSize) ? $this->getPage() * $this->getPageSize() : $this->resultSize )
      . ' von ' . $this->resultSize . ' Ergebnissen';
  }

  /* Link auf die erste Seite */
  protected function getPaginatorFirst(){
    if ( ($this->getPage() - ($this->getPaginatorSize() / 2 )) > 1 ){
      return $this->generatePaginatorLink($this->cObj->getSubpart($this->getPaginatorSubpart(), '###FIRST###') , 1 );		
    } else {
      return '';
    }
  }
  /* link auf die vorherige seite*/
  protected function getPaginatorPrev(){
    if ($this->getPage() > 1){
      return $this->generatePaginatorLink($this->cObj->getSubpart($this->getPaginatorSubpart(), '###PREVIOUS###') , $this->getPage() - 1 );		
    } else {
      return '';
    }
  }
  /* link auf die nächste seite*/
  protected function getPaginatorNext(){
    if ($this->getPage() * $this->getPageSize() < $this->resultSize ){
      return $this->generatePaginatorLink($this->cObj->getSubpart($this->getPaginatorSubpart(), '###NEXT###') , $this->getPage() + 1 );		
    } else {
      return '';
    }
  }

  /* link auf die letzte seite */
  protected function getPaginatorLast(){
    if ($this->getPage() + ($this->getPaginatorSize() / 2) < $this->getEndPage() ){
      return $this->generatePaginatorLink($this->cObj->getSubpart($this->getPaginatorSubpart(), '###LAST###') , $this->getEndPage() );		
    } else {
      return '';
    }
  }

  /* erzeugt einen paginator link  */	
  protected function generatePaginatorLink($content, $page){
    return $this->cObj->substituteSubpart($content,	'###LINKITEM###',
      $this->pi_linkTP_keepPIvars($this->cObj->getSubpart($content, '###LINKITEM###'), 
      array( 'page' => $page)));
  }	

  /* holt den teil des templates für den seiten-browser */
  protected function getPaginatorSubpart(){
    if($this->paginatorSubpart == null){
      $this->paginatorSubpart = $this->getSubpart('###PAGINATOR###');		
    }
    return $this->paginatorSubpart;
  }	

  /* erstellt die google map  und fügt die marker hinzu */
  protected function createMap (){
    /* diese function erzeugt die google map und konfiguriert die map */
    $map = $this->getConfiguredMap();
    $result = $this->fetchEntryList();
    if($result != null){
      $listContent = '';
      for($i=0; $entry = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result); $i++){
        $this->addMarkerToCach($entry);
      }
      $this->addAllMarkerToMap($map);
    }		
    return $this->cObj->substituteMarker($this->getSubpart('###MAP###'), '###MAPENTRY###', $map->drawMap() );
  }

  protected function addMarkerToCach($entry){
    $localCObj = t3lib_div::makeInstance('tslib_cObj');
    $localCObj->start($entry, $this->getSelectedTable());
    /* typoscript für die map  */
    $mapTs = $this->getMapTableTs();
    /* aus welchen feldern setzt sich die adresse zusammen */
    $addressFields = t3lib_div::trimExplode(',', $mapTs['address']);
    $address = '';
    /* adresse wird zusammen gesetzt */
    foreach($addressFields as $field ){
      $address .= $entry[$field] . ' ';
    }
    /* den title anhand des ts rendern */
    $title = $localCObj->cObjGetSingle( $mapTs['title'], $mapTs['title.']);
    /* die beschreibugn anhand des ts render*/
    $description = $localCObj->cObjGetSingle( $mapTs['description'], $mapTs['description.'] );
    /* den marker hinzufügen */
    $this->markerCache[] = new MarkerData($title, $description, $address);
    // /$map->addMarkerByString( $address, $title,$description);
  }

  /* fügt einen marker der map aufgrund des entry hinzu. der entry array eines datensatz */
  protected function addAllMarkerToMap(&$map){
    $size = sizeof($this->markerCache);
    for($i=0; $i<$size; $i++){

      $currentMarkerData = $this->markerCache[$i];
      if($currentMarkerData == null) {
        continue;
      }
      for($j=$i+1; $j<$size; $j++){
        $testMarkerData = $this->markerCache[$j];
        if($testMarkerData == null){
          continue;    
        }
        if($testMarkerData->getAddress() == $currentMarkerData->getAddress()){
          $currentMarkerData->setDescription( $currentMarkerData->getDescription() . "<br />" . $testMarkerData->getDescription() );
          $currentMarkerData->setTitle( $currentMarkerData->getTitle() . "<br />" . $testMarkerData->getTitle() );
          $this->markerCache[$j] = null;                    
        }
      }
    }
    foreach($this->markerCache as $markerData){
      if($markerData == null){
        continue;
      }
      $map->addMarkerByString( $markerData->getAddress(), $markerData->getTitle(), $markerData->getDescription());
    }
  }



  /* das marker array erzeugt aus einem Datensatz ein markerArray*/	
  protected function createMarkerArray($data, $list=true){
    $contentObject = t3lib_div::makeInstance('tslib_cObj');
    $contentObject->start($data, $this->getSelectedTable() );
    $markerArray = array();
    foreach($data as $column => $value){
      /* */
      $renderOutput = $this->renderColumn($column, $contentObject, $list);
      $markerArray['###'.strtoupper($column).'###'] = ($renderOutput)?$renderOutput:$value;
    }
    return $markerArray;
  }

  /* falls für eine feld TS definiert ist, so gibt diese funktion das gerenderte feld zurück ansonsten false */
  protected function renderColumn($col, $cObj, $list){
    $type = ($list) ? 'list.' : 'detail.';
    $tableTs = $this->getRenderTableTs();
    if($tableTs && is_array($tableTs[$type][$col.'.'])){
      $render = $cObj->cObjGetSingle($tableTs[$type][$col],$tableTs[$type][$col.'.']);
      return $render;
    }
    return false;
  }

  /* Holt das TS zum rendern von einträgen  */
  protected function getRenderTableTs(){
    return (is_array ($this->conf['renderer.'][$this->getSelectedTable().'.'])) ?
      $this->conf['renderer.'][$this->getSelectedTable().'.'] :
false;
  }

  /* hold das ts für die map erzeugung */
  protected function getMapTableTs(){
    return (is_array ($this->conf['map.'][$this->getSelectedTable().'.'])) ?
      $this->conf['map.'][$this->getSelectedTable().'.'] :
false;
  }

  /* erzeugt einen listen eintrag  */
  protected function getListEntry($entry, $i){
    $subpart = $this->getListEntryPart($i);
    return $this->cObj->substituteMarkerArray($subpart,
      $this->processListEntryMarkerArray($this->createMarkerArray($entry), $entry, $i));
  }


  protected function processListEntryMarkerArray($markerArray, $entry, $i){
    $detailColumnLink = $this->getDetailColumnLink();
    $markerArray['###'.strtoupper($detailColumnLink).'###'] = $this->pi_list_linkSingle($markerArray['###'.strtoupper($detailColumnLink).'###'],$entry['uid'],FALSE,array(),FALSE,$this->getDetailPageId() );
    return $markerArray;
  }


  /**
   * Holt den Tempalte-Subpart für einen Listeintrag. 
   * @return string Der Html-Subpart für einen Listeneingtrag. Kann von $i abhängen.
   * @param $i Zeilennummer
   */
  protected function getList($list){
    return $this->cObj->substituteSubpart($this->getListPart(),
      $this->getListEntryMarker(), 	$list);		
  }

  /* erzeugt einen kategorie eintrag */
  protected function getCategoryEntry($category, $i){
    $subpart = $this->getCategoryEntryPart($i);
    return $this->cObj->substituteMarkerArray($subpart, $this->processCategoryEntryMarkerArray($category, $i));	
  }

  protected function processCategoryEntryMarkerArray($category, $i){
    return array( $this->getCategoryNameMarker() => $this->getCategoryLink($category, $i));
  }

  /* erzeugt einen kategorielink */
  protected function getCategoryLink($category, $i){
    return $this->pi_linkTP_keepPIvars( implode( ' ', $category), array( 'category' => implode( ' ', $category)),0,0,$this->getCategoryListPageId() );
  }

  /* */
  protected function getCategories($categoryContent){
    return $this->cObj->substituteSubpart($this->getCategoryPart(),
      $this->getCategoryEntryMarker(), 	$categoryContent);	
  }


  protected function getCategoryEntryMarker(){
    return '###CATEGORYCONTENT###';
  }

  protected function getCategoryPart(){
    return $this->getSubpart('###CATEGORY###');
  }

  protected function getCategoryEntryPart($i){
    return $this->getSubpart('###CATEGORYENTRY_'. (($i % $this->numDiffCategoryEntries ) + 1 ).'###');
  }

  protected function getListEntryPart($i){
    return $this->getSubpart('###LISTENTRY_'. (($i % $this->numDiffEntries ) + 1 ).'###');
  }

  protected function getListPart(){
    return $this->getSubpart('###LIST###');
  }

  protected function getListEntryMarker(){
    return '###LISTENTRYCONTENT###';
  }

  protected function getCategoryNameMarker(){
    return '###CATEGORYNAME###';
  }

  protected function getDetailSubpart(){
    return $this->getSubpart('###DETAILVIEW###');
  }	


  /* erzeugt die suche  */		
  protected function createSearch(){
    $out = $this->getSubpart('###SEARCH###');		
    $out = $this->cObj->substituteMarker($out, '###SWORDS###', htmlspecialchars($this->getSearchWords() ));
    $out = $this->cObj->substituteMarker($out, '###FORMURL###', $this->pi_linkTP_keepPIvars_url(array(), 0, 1, $this->getSearchListPageId()));
    $out = $this->cObj->substituteMarker($out, '###SEARCH_BUTTON###', $this->pi_getLL('searchButtonLabel'));
    $out = $this->cObj->substituteMarker($out, '###SEARCH_AND###', $this->pi_getLL('searchOrLabel'));
    $out = $this->cObj->substituteMarker($out, '###SEARCH_OR###', $this->pi_getLL('searchAndLabel'));
    return $out;
  }

  protected function getSearchWords(){
    return $this->piVars['swords'];
  }


  protected function getSearchWordsArray(){
    $words = array();
    if($this->getSearchWords() ){
      $words = explode (' ', $this->getSearchWords());
    }
    return $words;
  }


  protected function getSearchConnection(){
    return ($this->piVars['sconnect'] == 'or') ? 'or' : 'and';
  }

  /**
   * Holt die Firmenliste aus der Datenbank und gibt ein mysql result zurück.
   * @return mysql_result
   */
  protected function fetchEntryList(){
    if($this->entryResult != null){
      return $this->entryResult;
    }
    $db = $GLOBALS['TYPO3_DB'];
    $catStatement = $this->getCategoryStatement();
    $searchStatement = $this->getSearchStatement();
    if($catStatement !== null && $searchStatement !== null ){
      $statement = $catStatement . ' and ' . $searchStatement;
    } else {
      $statement = ($catStatement !== null) ? $catStatement : $searchStatement;
    }
    $statement = ($statement == null) ? $this->getRestrictStatement() : $statement . ' and ' . $this->getRestrictStatement();
    if($this->getShowPaginator() ){ 
      $resultSizeRes = $db->sql_fetch_row($db->exec_SELECTquery('count(uid)',$this->getSelectedTable(),$statement ));
      $this->resultSize = $resultSizeRes[0];
      $limit = ($this->getPage()-1) * $this->getPageSize() . ', ' . $this->getPageSize();
      $result = $db->exec_SELECTquery('*',$this->getSelectedTable(),$statement,'', $this->getOrderBy(),  $limit);
    } else {
      $result = $db->exec_SELECTquery('*',$this->getSelectedTable(),$statement,'', $this->getOrderBy());
      $this->resultSize = $db->sql_num_rows($result);
    }
    return ($db->sql_num_rows($result) > 0) ? $result : null;
  }

  /* holt die list der categorien und gibt sie als mysql result zurück */
  protected function fetchCategoryList(){
    /**
     * Die Funktion fullQuoteStr schützt die Datenbank for SQLInjection
     * 
     */
    $statement = $this->getCategoryStatement();
    $statement = ($statement == null) ? $this->getRestrictStatement() : $statement . ' and ' . $this->getRestrictStatement();
    $result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('distinct ' . $this->getCategory(),$this->getSelectedTable(), $statement ,'',$this->getCategory() );
    $GLOBALS['TYPO3_DB']->sql_num_rows($result);
    if($GLOBALS['TYPO3_DB']->sql_num_rows($result)> 0){
      return $result;
    }
    return null;
  }

  /* holt eine eintrag für die detailansicht und gibt den eintrag zurück  */
  protected function fetchDetailEntry(){
    $id = intval($this->piVars['showUid']);
    if($id){
      return $this->pi_getRecord($this->getSelectedTable(),$id);
    }
    return null;
  }

  /* erzeugt das where statement für die listen- und kategorieansicht, wenn die listenansicht durch kategorieen eingeschränkt ist */
  protected function getCategoryStatement(){
    $categoryArray = $this->getShowCategory();
    if(sizeof($categoryArray) == 0) {
      return null;
    }
    for($i=0; $i < sizeof($categoryArray); $i++){
      $categoryArray[$i] =  $GLOBALS['TYPO3_DB']->fullQuoteStr($categoryArray[$i], $this->getSelectedTable());
    }
    if($this->getCategory() == null){
      echo '<div style="color:red">ERROR: Category view is selected but no Category is choosen. Select a category in the category tab.</div>';
    }
    return ' ' . $this->getCategory() . ' in  (' . implode(',', $categoryArray) . ') ';
  }

  /* erzeugt das where statement für die suche */
  protected function getSearchStatement(){
    $searchWords = $this->getSearchWordsArray();
    $searchFields = $this->getSearchIn();
    $statementArray = array();
    if(sizeof($searchWords ) > 0 && sizeof($searchFields)){
      for($i=0; $i < sizeof($searchWords); $i++){
        $wordStatemantArray = array();
        for($j=0; $j< sizeof($searchFields); $j++){
          $wordStatemantArray[$j] = 'LOWER('.$searchFields[$j]. ') like \'%' . strtolower($searchWords[$i]) . '%\'';  
        }
        $statementArray[$i] = ' ('.implode( ' or ',$wordStatemantArray).') ';
      }
    }
    if(sizeof($statementArray)> 0){
      return ' ('.implode( ' '.$this->getSearchConnection().' ',$statementArray).')';
    } else {
      return null;
    }
  }

  /* holt einen subpart aus dem template */
  protected function getSubpart($subPart){
    if($this->templateSource === null) {
      $this->templateSource = $this->cObj->fileResource( $this->getTemplateFile() );
      if(! $this->templateSource ){
        return false;
      }
    }
    return $this->cObj->getSubpart($this->templateSource, $subPart);
  }


  /**
   * Config getter Methods
   /**
    *  holt die template datei
    * @return 
   */	
  protected function getTemplateFile(){
    $flexFormTemplateFile = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'template_file','sDEF');
    if($flexFormTemplateFile){
      return $this->uploadDir . $flexFormTemplateFile;
    } else {
      return $this->conf['templateFile'];        	
    }
  }

  protected function getConfiguredMap(){
    include_once(t3lib_extMgm::extPath('wec_map').'map_service/google/class.tx_wecmap_map_google.php');
    $className = t3lib_div::makeInstanceClassName("tx_wecmap_map_google");
    $map = new $className($this->getApiKey(), $this->getMapWidth(), $this->getMapHeight(), '', '', '', 'map' . $this->cObj->data['uid'] );

    $map->addControl($this->getScale());
    $map->addControl($this->getOverviewMap());
    $map->setType($this->getInitialMapType());
    $map->addControl($this->getMapType());
    $map->addControl($this->getMapControlSize());
    if($this->getShowInfoOnLoad() ){
      $map->showInfoOnLoad();	
    }
    if($mapType) $map->addControl('mapType');
    if($initialMapType) $map->setType($initialMapType);
    return $map;
  }


  protected function getMapWidth() {
    $felxValue = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'mapWidth', 'MapControls');
    return ($felxValue) ? $felxValue : 500;	
  }

  protected function getMapHeight() {
    $felxValue = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'mapHeight', 'MapControls');
    return ($felxValue) ? $felxValue : 500;	
  }

  protected function getInitialMapType(){
    $felxValue = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'initialMapType', 'MapControls');
    return ($felxValue) ? $felxValue : 'G_NORMAL_MAP';	
  }

  protected function getShowInfoOnLoad(){
    return $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'showInfoOnLoad', 'MapControls');
  }

  protected function getShowDirections(){
    return $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'showDirections', 'MapControls');
  }

  protected function getMapControlSize(){
    $felxValue = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'mapControlSize', 'MapControls');
    return ($felxValue != 'none') ? $felxValue : '';		
  }

  protected function getOverviewMap(){
    return ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'overviewMap', 'MapControls')) ? 'overviewMap':'';
  }

  protected function getScale(){
    return ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'scale', 'MapControls')) ? 'scale':'';
  }

  protected function getMapType(){
    return ($this->pi_getFFvalue($this->cObj->data['pi_flexform'],'mapType', 'MapControls'))?'mapType':'';
  }


  protected function getApiKey(){
    return $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'apiKey', 'MapControls');
  }

  /**
   * holt die id der detail seite
   * @return 
   */
  protected function getDetailPageId(){
    return $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'detail_page','List');
  }

  /**
   * holt die views
   * @return 
   */
  protected function getViewConfig(){
    $views = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'what_to_display','sDEF');
    if(! $views){
      return 'LIST';
    }
    return $views;
  }


  protected function getCategory(){
    return $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'category','Category');
  }


  protected function getShowCategory(){
    $urlCategory = trim($this->piVars['category']);
    $formCategory = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'show_category','Category');
    $categoryArray = array();
    if($urlCategory || $formCategory){
      if($formCategory){
        $categoryArray = explode(',', $formCategory);
      }
      if($urlCategory){
        $categoryArray[] = $urlCategory;
      }
    }
    return $categoryArray;
  }


  protected function getOrderBy(){
    $order = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'order_by','List');
    if($order) {
      $order .= ' ' .$this->pi_getFFvalue($this->cObj->data['pi_flexform'],'order_by_asc','sDEF');
    }
    return $order;
  }

  protected function getCategoryListPageId(){
    return $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'list_page','Category');
  }

  protected function getSearchListPageId(){
    return $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'list_page','Search');
  }

  protected function getRestrictStatement(){
    $where = ' deleted=0 and hidden=0 ';
    $pids = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'pages','sDEF');
    $recursive =  $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'recursive','sDEF');
    if($pids){
      $where .= " and pid IN (".$this->pi_getPidList($pids, $recursive).") ";
    }
    return $where;
  }

  protected function getSearchIn(){
    $searchin = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'search_in','Search');
    $searchFields = array();
    if($searchin){
      $searchFields = explode(',', $searchin);
    }
    return $searchFields;
  }

  protected function getDetailColumnLink(){
    return $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'detail_column_link','List');
  }

  protected function getSelectedTable(){
    return $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'select_table','sDEF');
  }

  protected function getPage(){
    $page = intval($this->piVars['page']);
    return ($page > 1) ? $page : 1;
  }

  protected function getPageSize(){
    $pageSize = intval($this->piVars['pageSize']);
    $pageSize = ($pageSize) ? $pageSize : $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'pageSize','List');
    return ($pageSize > 10) ? $pageSize : 10;
  }

  protected function getPaginatorSize(){
    $paginatorSize = $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'paginatorSize','List');
    $paginatorSize = ($paginatorSize) ? $paginatorSize : $this->conf['paginatorSize'];
    return ($paginatorSize) ? $paginatorSize : 10;
  }

  protected function getShowPaginator(){
    return $this->pi_getFFvalue($this->cObj->data['pi_flexform'],'showPaginator','List');
  }

  protected function getResultSize(){
    return $this->resultSize;
  }

  protected function getEndPage(){
    return ceil($this->resultSize / $this->getPageSize());
  }
}

class MarkerData {

  private $address;
  private $description;
  private $title;

  public function __construct($title, $description, $address){
    $this->address = $address;
    $this->description = $description;
    $this->title = $title;
  }

  public function getAddress(){
    return $this->address;
  }

  public function getDescription(){
    return $this->description;
  }

  public function getTitle(){
    return $this->title;
  }

  public function setDescription($value) {
    $this->description = $this->cleanValue($value);
  }

  public function setTitle($value){
    $this->title = $this->cleanValue($value);
  }

  public function cleanValue($value){
    die("called" );
    $value = str_replace("a", " ", $value);
    $value = nlbr($value, true);    
    return $value;
  }
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/qz_companylist/pi1/class.tx_qzcompanylist_pi1.php'])	{
  include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/qz_companylist/pi1/class.tx_qzcompanylist_pi1.php']);
}


