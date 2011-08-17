<?php


class Calendar_Config extends Tinebase_Config_Abstract
{
    /**
     * Fixed Calendars
     * 
     * @var string
     */
    const FIXED_CALENDARS = 'fixedCalendars';
    
    /**
     * Attendee Status Available
     * 
     * @var string
     */
    const ATTENDEE_STATUS = 'attendeeStatus';
    
    /**
     * Attendee Roles Available
     * 
     * @var string
     */
    const ATTENDEE_ROLES = 'attendeeRoles';
    
    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Definition::$_properties
     */
    protected static $_properties = array(
        self::FIXED_CALENDARS => array(
                                   //_('Fixed Calendars')
            'label'                 => 'Fixed Calendars',
                                   //_('Calendars always selected regardles of all filter parameters. A valid use case might be to force the display of an certain holiday calendar.')
            'description'           => 'Calendars always selected regardles of all filter parameters. A valid use case might be to force the display of an certain holiday calendar.',
            'type'                  => 'array',
            'contents'              => 'string', // in fact this are ids of Tinebase_Model_Container of app Calendar and we might what to have te ui to autocreate pickers panel here? x-type? -> later
            'clientRegistryInclude' => TRUE,
        
        ),
        self::ATTENDEE_STATUS => array(
                                   //_('Attendee Status Available')
            'label'                 => 'Attendee Status Available',
                                   //_('Possible event attendee status. Please note that additional attendee status might impact other calendar systems on export or syncronisation.')
            'description'           => 'Possible event attendee status. Please note that additional attendee status might impact other calendar systems on export or syncronisation.',
            'type'                  => 'object',
            'class'                 => 'Tinebase_Model_KeyFieldConfig',
            'clientRegistryInclude' => TRUE,
        ),
        self::ATTENDEE_ROLES => array(
                                   //_('Attendee Roles Available')
            'label'                 => 'Attendee Roles Available',
                                   //_('Possible event attendee roles. Please note that additional attendee roles might impact other calendar systems on export or syncronisation.')
            'description'           => 'Possible event attendee roles. Please note that additional attendee roles might impact other calendar systems on export or syncronisation.',
            'type'                  => 'object',
            'class'                 => 'Tinebase_Model_KeyFieldConfig',
            'clientRegistryInclude' => TRUE,
        ),
    );
    
    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Abstract::$_appName
     */
    protected $_appName = 'Calendar';
    
    /**
     * holds the instance of the singleton
     *
     * @var Tinebase_Config
     */
    private static $_instance = NULL;
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */    
    private function __construct() {}
    
    /**
     * the constructor
     *
     * don't use the constructor. use the singleton 
     */    
    private function __clone() {}
    
    /**
     * Returns instance of Tinebase_Config
     *
     * @return Tinebase_Config
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new self();
        }
        
        return self::$_instance;
    }
    
    /**
     * (non-PHPdoc)
     * @see tine20/Tinebase/Config/Abstract::getProperties()
     */
    public static function getProperties()
    {
        return self::$_properties;
    }
    
}