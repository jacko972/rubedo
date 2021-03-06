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

require_once ('AbstractExtLoaderController.php');

/**
 * Controller handling media finder for contribution
 *
 * @author jbourdin
 * @category Rubedo
 * @package Rubedo
 */
class Backoffice_ExtFinderController extends Backoffice_AbstractExtLoaderController
{

    /**
     * Default Action, return the Ext/Js HTML loader
     *
     * @todo handle extjs options (debug or not, CDN or not).
     */
    public function indexAction ()
    {
        $this->_auth = Rubedo\Services\Manager::getService('Authentication');
        
        if (! $this->_auth->getIdentity()) {
            $this->_helper->redirector->gotoUrl("/backoffice/login");
        }
        
        $this->loadExtApps();
    }
}

