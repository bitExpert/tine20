<?php
/**
 * class to hold Sieve Rule data
 * 
 * @package     Felamimail
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id$
 * 
 */

/**
 * class to hold Rule data
 * 
 * @property    integer  order
 * @property    array  action       array('type', 'argument')
 * @property    array  conditions   array( 0 => array('test', 'comperator', 'header', 'key'), 1 => (...))
 * @package     Felamimail
 */
class Felamimail_Model_Sieve_Rule extends Tinebase_Record_Abstract
{  
    /**
     * key in $_validators/$_properties array for the field which 
     * represents the identifier
     * 
     * @var string
     */    
    protected $_identifier = 'id';    
    
    /**
     * application the record belongs to
     *
     * @var string
     */
    protected $_application = 'Felamimail';

    /**
     * list of zend validator
     * 
     * this validators get used when validating user generated content with Zend_Input_Filter
     *
     * @var array
     */
    protected $_validators = array(
        'id'                    => array(Zend_Filter_Input::ALLOW_EMPTY => true), // account id
        'order'                 => array(Zend_Filter_Input::ALLOW_EMPTY => true),
        'action'                => array(Zend_Filter_Input::ALLOW_EMPTY => true),    
        'conditions'            => array(Zend_Filter_Input::ALLOW_EMPTY => true),    
    );
    
    /**
     * set from sieve rule object
     * 
     * @param Felamimail_Sieve_Rule $fsr
     */
    public function setFromFSR(Felamimail_Sieve_Rule $fsr)
    {
        $this->setFromArray($fsv->toArray());
    }
    
    /**
     * get sieve rule object
     * 
     * @return Felamimail_Sieve_Rule
     */
    public function getFSR()
    {
        $fsr = new Felamimail_Sieve_Rule();
        $fsr->setEnabled($this->enabled);

        $fsra = new Felamimail_Sieve_Rule_Action();
        $fsra->setType($this->action['type'])
             ->setArgument($this->action['argument']);
        $fsr->setAction($fsra);
        
        foreach ($this->conditions as $condition) {
            $fsrc = new Felamimail_Sieve_Rule_Condition();
            $fsrc->setTest($condition['test'])
                 ->setComperator($condition['comperator'])
                 ->setHeader($condition['header'])
                 ->setKey($condition['key']);
            $fsr->addCondition($fsrc);
        } 
            
        return $fsr;
    }
    
}
