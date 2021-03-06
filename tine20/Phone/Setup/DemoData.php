<?php
/**
 * Tine 2.0
 *
 * @package     Phone
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Alexander Stintzing <a.stintzing@metaways.de>
 * @copyright   Copyright (c) 2013 Metaways Infosystems GmbH (http://www.metaways.de)
 *
 */

/**
 * class for Phone demo data
 *
 * @package Setup
 */
class Phone_Setup_DemoData extends Tinebase_Setup_DemoData_Abstract
{
    /**
     * holds the instance of the singleton
     *
     * @var Phone_Setup_DemoData
     */
    private static $_instance = NULL;

    /**
     * 
     * required apps
     * @var array
     */
    protected static $_requiredApplications = array('Voipmanager');
    
    /**
     * the application name to work on
     * 
     * @var string
     */
    protected $_appName         = 'Phone';
    
    /**
     * models to work on
     * @var array
     */
    protected $_models = array();

    /**
     * the constructor
     *
     */
    private function __construct()
    {
    }
    
    /**
     * the singleton pattern
     *
     * @return Timetracker_Setup_DemoData
     */
    public static function getInstance()
    {
        if (self::$_instance === NULL) {
            self::$_instance = new self();
        }
    
        return self::$_instance;
    }
}
