<?php
/**
 * Rubedo -- ECM solution
 * Copyright (c) 2013, WebTales (http://www.webtales.fr/).
 * All rights reserved.
 * licensing@webtales.fr
 *
 * Open Source License
 * ------------------------------------------------------------------------------------------
 * Rubedo is licensed under the terms of the Open Source GPL 3.0 license. 
 *
 * @category   Rubedo
 * @package    Rubedo
 * @copyright  Copyright (c) 2012-2013 WebTales (http://www.webtales.fr)
 * @license    http://www.gnu.org/licenses/gpl.html Open Source GPL 3.0 license
 */
Use Rubedo\Services\Manager;


require_once ('AbstractController.php');

/**
 *
 * @author jbourdin
 * @category Rubedo
 * @package Rubedo
 */
class Blocks_GeoSearchController extends Blocks_AbstractController
{

    protected $_option = 'geo';
    
    public function init(){
        Rubedo\Elastic\DataSearch::setIsFrontEnd(true);
        parent::init();
    }

    public function indexAction ()
    {
        $googleMapsKey = $this->getRequest()->getParam('googleMapsKey');
        $params = $this->getRequest()->getParams();

        $results = $params;
        $results['blockConfig'] = $params['block-config'];
        $results['encodedConfig']=Zend_Json::encode($results['blockConfig']);
        $results['displayTitle'] = $this->getParam('displayTitle');
        $results['blockTitle'] = $this->getParam('blockTitle');
        $template = Manager::getService('FrontOfficeTemplates')->getFileThemePath("blocks/geoSearch.html.twig");
        $css = array();
        $js = array(
            'https://maps.googleapis.com/maps/api/js?key='.$googleMapsKey.'&libraries=places&sensor=true&language='.Manager::getService('CurrentLocalization')->getCurrentLocalization(),
            '/templates/' . Manager::getService('FrontOfficeTemplates')->getFileThemePath("js/geosearch.js"),
            'http://google-maps-utility-library-v3.googlecode.com/svn/tags/markerclustererplus/2.0.9/src/markerclusterer_packed.js',
            'http://google-maps-utility-library-v3.googlecode.com/svn/tags/markerwithlabel/1.1.8/src/markerwithlabel_packed.js',
            );
        $this->_sendResponse($results, $template, $css, $js);
    }

    public function xhrSearchAction ()
    {
        
        // get params
        $params = $this->getRequest()->getParams();

        $params['block-config']=array();
        $params['block-config']['displayedFacets']=isset($params['displayedFacets']) ? $params['displayedFacets'] : array();
        $params['block-config']['facetOverrides']=isset($params['facetOverrides']) ? $params['facetOverrides'] : \Zend_Json::encode(array());
        $params['block-config']['displayMode']=isset($params['displayMode']) ? $params['displayMode'] : 'standard';
        $params['block-config']['autoComplete']=isset($params['autoComplete']) ? $params['autoComplete'] : false;
        
        // get option : all, dam, content, geo
        if (isset($params['option'])) {
            $this->_option = $params['option'];
        }
        $facetsToHide = array();
        if (isset($params['constrainToSite']) && $params['constrainToSite'] === 'true') {
            $currentPageId = $this->getRequest()->getParam('current-page');
            $currentPage = Rubedo\Services\Manager::getService('Pages')->findById($currentPageId);
            $siteId = $currentPage['site'];
            $facetsToHide[] = "navigation";
            if (! isset($params['navigation'])) {
                $params['navigation'] = array();
            }
            if (! in_array($siteId, $params['navigation'])) {
                $params['navigation'][] = $siteId;
            }
        }
        // apply predefined facets
        if (isset($params['predefinedFacets'])) {
            $predefParamsArray = \Zend_Json::decode($params['predefinedFacets']);
            if (is_array($predefParamsArray)){
                foreach ($predefParamsArray as $key => $value) {
                    if (!isset($params[$key]) or !in_array($value,$params[$key])) $params[$key][] = $value;
                    $facetsToHide[] = $value;
                }
            }
        }
        
        $facetsToHide = array_unique($facetsToHide);
        
        Rubedo\Elastic\DataSearch::setIsFrontEnd(true);
        
        $query = Manager::getService('ElasticDataSearch');
        
        $query->init();
        $results = $query->search($params, $this->_option, false);
        $results = $this->_clusterResults($results);
        
        $results['displayMode'] =  $params['block-config']['displayMode'];
        
        $results['autoComplete'] =  $params['block-config']['autoComplete'];
        
        $results['facetsToHide'] = $facetsToHide;
        $results['searchParams']=\Zend_Json::encode($params);
        
        $activeFacetsTemplate = Manager::getService('FrontOfficeTemplates')->getFileThemePath("blocks/geoSearch/activeFacets.html.twig");
        $facetsTemplate = Manager::getService('FrontOfficeTemplates')->getFileThemePath("blocks/geoSearch/facets.html.twig");
        
        
        $results['activeFacetsHtml'] = Manager::getService('FrontOfficeTemplates')->render($activeFacetsTemplate, $results);
        $results['facetsHtml'] = Manager::getService('FrontOfficeTemplates')->render($facetsTemplate, $results);
        $results['success'] = true;
        $results['message'] = 'OK';
        unset($results['searchParams']);
        
        $this->_helper->json($results);
    }

    public function xhrGetSuggestsAction ()
    {
        // get search parameters
        
        $params = \Zend_Json::decode($this->getRequest()->getParam('searchParams'));
        
        // get current language
        $currentLocale = Manager::getService('CurrentLocalization')->getCurrentLocalization();
        
        // set query
        $params['query'] = $this->getRequest()->getParam('query');
         
        // set field for autocomplete
        $params['field'] = 'autocomplete_'.$currentLocale;
                 
        $elasticaQuery = Manager::getService('ElasticDataSearch');
        $elasticaQuery->init();
        
        $suggestTerms = $elasticaQuery->search($params,'geosuggest');
        
        $data = array(
                'terms' => $suggestTerms
        );
        $this->_helper->json($data);    

    }
    
    protected function _clusterResults ($results)
    {
        // return $results;
        $tmpResults = array();
        foreach ($results['data'] as $item) {
            $subkey = $item['fields.position.location.coordinates'];
            if (! isset($tmpResults[$subkey])) {
                $tmpResults[$subkey]['position_location'] = $item['fields.position.location.coordinates'];
                $tmpResults[$subkey]['count'] = 0;
                $tmpResults[$subkey]['id'] = '';
            }
            unset($item['fields.position.location.coordinates']);
            $tmpResults[$subkey]['count'] ++;
            if ($tmpResults[$subkey]['count'] > 1) {
                $tmpResults[$subkey]['title'] = $tmpResults[$subkey]['count'];
            } else {
                $tmpResults[$subkey]['title'] = $item['title'];
            }
            $tmpResults[$subkey]['id'] .= $item['id'];
            $tmpResults[$subkey]['idArray'][] = $item['id'];
        }
        $results['data'] = array_values($tmpResults);
        return $results;
    }

    public function xhrGetDetailAction ()
    {
        $templateService = Manager::getService('FrontOfficeTemplates');
        $sessionService = Manager::getService('Session');
        // get params
        $idArray = $this->getRequest()->getParam('idArray');
        $itemHtml = '';
        foreach ($idArray as $id) {
            $entity = Rubedo\Services\Manager::getService('Contents')->findById($id, true, false);
            if (isset($entity)) {
                $type = "content";
            } else {
                $entity = Rubedo\Services\Manager::getService('Dam')->findById($id);
                $type = "dam";
            }
            if (isset($entity)) {
                if ($type == "content") {
                    $intermedVar = Rubedo\Services\Manager::getService('ContentTypes')->findById($entity['typeId']);
                    $entity['type'] = $intermedVar['type'];
                } else {
                    $intermedVar = Rubedo\Services\Manager::getService('ContentTypes')->findById($entity['typeId']);
                    $entity['type'] = $intermedVar['type'];
                }
                
                if (isset($entity['code']) && !empty($entity['code'])) {
                    $templateName = $entity['code'] . ".html.twig";
                } else {
                    $templateName = preg_replace('#[^a-zA-Z]#', '', $entity["type"]);
                    $templateName .= ".html.twig";
                }
                $contentOrDamTemplate = $templateService->getFileThemePath("blocks/geoSearch/single/" . $templateName);
                
                if (! is_file($templateService->getTemplateDir() . '/' . $contentOrDamTemplate)) {
                    $contentOrDamTemplate = $templateService->getFileThemePath("blocks/geoSearch/contentOrDam.html.twig");
                }
                
                $entity['objectType'] = $type;
                
                $termsArray = array();
                if (isset($entity['taxonomy'])) {
                    if (is_array($entity['taxonomy'])) {
                        foreach ($entity['taxonomy'] as $key => $terms) {
                            if ($key == 'navigation') {
                                continue;
                            }
                            if(is_array($terms)) {
                                foreach ($terms as $term) {
                                    $intermedTerm = Manager::getService('TaxonomyTerms')->findById($term);
                                    if (! empty($intermedTerm)) {
                                        $termsArray[] = $intermedTerm['text'];
                                    }
                                }
                            }
                        }
                    }
                }
                
                $twigVars = array();
                $twigVars['result'] = $entity;
                $twigVars['lang'] = Manager::getService('CurrentLocalization')->getCurrentLocalization();
                $twigVars['result']['terms'] = $termsArray;
                
                $itemHtml .= $templateService->render($contentOrDamTemplate, $twigVars);
            }
        }
        
        $result = array();
        
        if ($itemHtml !== '') {
            $markerTemplate = $templateService->getFileThemePath("blocks/geoSearch/marker.html.twig");
            $html = $templateService->render($markerTemplate, array(
                'content' => $itemHtml
            ));
            $result["data"] = $html;
            $result['success'] = true;
            $result['message'] = 'OK';
        } else {
            $result['success'] = false;
            $result['message'] = "No entity found";
        }
        $this->_helper->json($result);
    }
}
