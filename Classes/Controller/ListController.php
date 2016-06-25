<?php
/** Apache 2.0 **/

use \TYPO3\CMS\Core\Utility\GeneralUtility;
use \TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
/*
 *
 * @author Peter Quiel - peter.quiel@gmail.com
 */
class Tx_ContentLister_Controller_ListController extends ActionController {
    public $uploadDir = 'uploads/tx_contentlister/';

    public function initializeAction(){
        t3lib_utility_Debug::debug($this->settings);
    }
    
    /**
     *  @param string $searchword
     *  @param string $searchoperator
     *   */
    public function searchAction($searchword='*', $searchoperator='and') {
        t3lib_utility_Debug::debug("searchword:". $searchword . " op:" . $searchoperator);
        $this->initView();
        $this->initSearch();
        $this->view->assign('searchword', $searchword);
        $this->view->assign('searchoperator', $searchoperator);
        $this->view->assign('renderList', true);
        if($this->settings['flexform']['show_search']){
            $this->view->assign('renderSearch', true);
        }
        $this->view->assign('listEntries', $this->fetchEntryList());
    }

    public function listAction() {
        $this->initView();
        $this->initSearch();
        t3lib_utility_Debug::debug($this->request->getArguments());
        $this->view->assign('url', $this->uriBuilder->getRequest()->getRequestUri());

        $this->view->assign('renderList', true);
        $this->view->assign('listEntries', $this->fetchEntryList());
    }

    public function categoryAction() {
        $this->initView();
        $this->view->assign('renderCategory', true);
    }

    public function detailAction() {
        $this->initView();
        $this->view->assign('renderDetail', true);
        if($this->request->hasArgument('showUid')){
            $pageRepository = t3lib_div::makeInstance('\TYPO3\CMS\Frontend\Page\PageRepository');
            $this->view->assign('detailEntry', $pageRepository->checkRecord($this->getSelectedTable(), $this->request->getArgument('showUid')));
        } else{
            // todo redirect to list view
        }
    }

    private function initSearch() {
        if($this->settings['flexform']['show_search']){
            $this->view->assign('renderSearch', true);
            $this->view->assign('searchoperatordata', array('or' => 'Oder', 'and' => 'Und'));
        }
    }
    private function initView(){
        $this->view->assign('settings', $this->settings);

        $templateFile = $this->getTemplateFile();
        if (!$templateFile) {
            $templateFile = t3lib_extMgm::extPath('content_lister') . 'Resources/Private/Templates/List/Index.html';
        }
        $this->view->setTemplatePathAndFilename($templateFile);
    }

    protected function getTemplateFile(){
        $flexFormTemplateFile = $this->settings['flexform']['template_file'];
        if($flexFormTemplateFile){
            return $this->uploadDir . $flexFormTemplateFile;
        } else {
            return $this->conf['templateFile'];
        }
        return null;
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
        $catCriteria = $this->getCategoryCriteria();
        $searchCriteria = $this->getSearchCriteria();
        if($catCriteria !== null && $searchCriteria !== null ){
            $statement = $catCriteria . ' and ' . $searchCriteria;
        } else {
            $statement = ($catCriteria !== null) ? $catCriteria : $searchCriteria;
        }
        $statement = ($statement == null) ? $this->getRestrictStatement() : $statement . ' and ' . $this->getRestrictStatement();
        if($this->getShowPaginator()){
            $resultSizeRes = $db->sql_fetch_row($db->exec_SELECTquery('count(uid)',$this->getSelectedTable(),$statement ));
            $this->resultSize = $resultSizeRes[0];
            $limit = ($this->getPage()-1) * $this->getPageSize() . ', ' . $this->getPageSize();
            $result = $db->exec_SELECTquery('*',$this->getSelectedTable(),$statement,'', $this->getOrderBy(),  $limit);
        } else {
            $result = $db->exec_SELECTquery('*',$this->getSelectedTable(),$statement,'', $this->getOrderBy());
            $this->resultSize = $db->sql_num_rows($result);
        }
        t3lib_utility_Debug::debug($this->getSelectedTable());
        return ($db->sql_num_rows($result) > 0) ? $result : null;
    }

    protected function getSelectedTable(){
        return $this->settings['flexform']['select_table'];
    }

    protected function getShowPaginator(){
        return $this->settings['flexform']['showPaginator'];
    }

    protected function getPage(){
        $page = 1;
        if($this->request->hasArgument('page')) {
            $page = intval($this->request->getArgument('page'));
        }
        return ($page > 1) ? $page : 1;
    }

    protected function getPageSize(){
        $pageSize = 1;
        if($this->request->hasArgument('pageSize')){
            $pageSize = intval($this->request->getArgument('pageSize'));
        }
        $pageSize = ($pageSize) ? $pageSize : $this->settings['flexform']['pageSize'];
        return ($pageSize > 5) ? $pageSize : 5;
    }

    /* holt die list der categorien und gibt sie als mysql result zurück */
    protected function fetchCategoryList(){
        /**
         * Die Funktion fullQuoteStr schützt die Datenbank for SQLInjection
         *
         */
        $statement = $this->getCategoryCriteria();
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
        $id = intval($this->request->getArgmuent('showUid'));
        if($id){
            return $this->pi_getRecord($this->getSelectedTable(),$id);
        }
        return null;
    }

    /* erzeugt das where statement für die listen- und kategorieansicht, wenn die listenansicht durch kategorieen eingeschränkt ist */
    protected function getCategoryCriteria(){
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
    protected function getSearchCriteria(){
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

    protected function getSearchWordsArray(){
        $words = array();
        if($this->getSearchWords() ){
            $words = explode (' ', $this->getSearchWords());
        }
        return $words;
    }

    protected function getSearchWords(){
        if($this->request->hasArgument('swords')){
            return $this->request->getArgmuent('swords');
        }
        return null;
    }

    protected function getCategory(){
        return $this->settings['flexform']['category'];
    }


    protected function getShowCategory(){
        $formCategory = $this->settings['flexform']['show_category'];
        $categoryArray = array();
        if($this->request->hasArgument('category') || $formCategory){
            if($formCategory){
                $categoryArray = explode(',', $formCategory);
            }
            if($this->request->hasArgument('category')){
                $categoryArray[] = trim($this->request->getArgument('category'));
            }
        }
        return $categoryArray;
    }


    protected function getOrderBy(){
        $order = $this->settings['flexform']['order_by'];
        if($order) {
            $order .= ' ' .$this->settings['flexform']['order_by_asc'];
        }
        return $order;
    }

    protected function getRestrictStatement(){
        $where = ' deleted=0 and hidden=0 ';
        $pids = $this->settings['flexform']['pages'];
        $recursive =  $this->settings['flexform']['recursive'];
        if($pids){
            $pi = $this->objectManager->create('TYPO3\CMS\Frontend\Plugin\AbstractPlugin');
            $pi->cObj = $this->configurationManager->getContentObject();
            $pidList = $pi->pi_getPidList($pids, $recursive);
            $where .= " and pid IN (".$pidList.") ";
        }
        return $where;
    }

    protected function getSearchIn(){
        $searchin = $this->settings['flexform']['search_in'];
        $searchFields = array();
        if($searchin){
            $searchFields = explode(',', $searchin);
        }
        return $searchFields;
    }
}
?>
