<?php
/**
 * Tine 2.0
 * 
 * @package     Tinebase
 * @subpackage  Container
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @copyright   Copyright (c) 2007-2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @version     $Id$
 */

/**
 * this class handles access rights(grants) to containers
 * 
 * any record in Tine 2.0 is tied to a container. the rights of an account on a record gets 
 * calculated by the grants given to this account on the container holding the record (if you know what i mean ;-))
 * 
 * @package     Tinebase
 * @subpackage  Acl
 */
class Tinebase_Container
{
    /**
     * the table object for the SQL_TABLE_PREFIX .container table
     *
     * @var Zend_Db_Table_Abstract
     */
    protected $containerTable;

    /**
     * the table object for the SQL_TABLE_PREFIX . container_acl table
     *
     * @var Zend_Db_Table_Abstract
     */
    protected $containerAclTable;

    /**
     * the constructor
     */
    private function __construct() {
        $this->containerTable = new Tinebase_Db_Table(array('name' => SQL_TABLE_PREFIX . 'container'));
        $this->containerAclTable = new Tinebase_Db_Table(array('name' => SQL_TABLE_PREFIX . 'container_acl'));
    }
    /**
     * don't clone. Use the singleton.
     *
     */
    private function __clone() {}

    /**
     * holdes the instance of the singleton
     *
     * @var Tinebase_Container
     */
    private static $_instance = NULL;
    
    /**
     * the singleton pattern
     *
     * @return Tinebase_Container
     */
    public static function getInstance() 
    {
        if (self::$_instance === NULL) {
            self::$_instance = new Tinebase_Container;
        }
        
        return self::$_instance;
    }

    /**
     * creates a new container
     *
     * @param Tinebase_Model_Container $_container the new container
     * @param Tinebase_Record_RecordSet $_grants the grants for the new folder 
     * @param bool  $_ignoreAcl
     * @param integer $_accountId
     * @return Tinebase_Model_Container the newly created container
     */
    public function addContainer(Tinebase_Model_Container $_container, $_grants = NULL, $_ignoreAcl = FALSE, $_accountId = NULL)
    {
        if(!$_container->isValid()) {
            throw new Exception('invalid container object supplied');
        }
        
        if ( $_accountId !== NULL ) {
            $accountId = $_accountId;
        } else {
            $accountId = Zend_Registry::get('currentAccount')->getId();
        }
        
        if($_ignoreAcl !== TRUE) {
            switch($_container->type) {
                case Tinebase_Model_Container::TYPE_PERSONAL:
                    // is the user allowed to create personal container?
                    //Zend_Registry::get('logger')->debug(__METHOD__ . '::' . __LINE__ . 'create container: ' . print_r($_container->toArray(), true));
                    break;
                    
                case Tinebase_Model_Container::TYPE_SHARED:
                    $application = Tinebase_Application::getInstance()->getApplicationById($_container->application_id);
                    if(!Zend_Registry::get('currentAccount')->hasRight((string) $application, Tinebase_Acl_Rights::ADMIN)) {
                        throw new Exception('permission to add shared container denied');
                    }
                    break;
                    
                default:
                    throw new Exception('can add personal or shared folders only when ignoring Acl');
                    break;
            }
        }
        
        $data = array(
            'name'              => $_container->name,
            'type'              => $_container->type,
            'backend'           => $_container->backend,
            'application_id'    => $_container->application_id
        );
        
        $containerId = $_container->getId();
        
        if($containerId === NULL) {
            $containerId = $this->containerTable->insert($data);
            if ($containerId === NULL) {
                $containerId = $this->containerTable->getAdapter()->lastSequenceId(substr(SQL_TABLE_PREFIX . 'container', 0, 26) . '_seq');
            }
        } else {
            $data['id'] = $containerId;
            $this->containerTable->insert($data);
        }
        
        if($containerId < 1) {
            throw new UnexpectedValueException('$containerId can not be 0');
        }
        
        $container = $this->getContainerById($containerId);
        
        if($_grants === NULL) {
            if($container->type === Tinebase_Model_Container::TYPE_SHARED) {
    
                // add all grants to creator
                // add read grants to any other user
                $grants = new Tinebase_Record_RecordSet('Tinebase_Model_Grants', array(
                    array(
                        'account_id'     => $accountId,
                        'account_type'   => 'user',
                        //'account_name'   => 'not used',
                        'readGrant'      => true,
                        'addGrant'       => true,
                        'editGrant'      => true,
                        'deleteGrant'    => true,
                        'adminGrant'     => true
                    ),            
                    array(
                        'account_id'      => 0,
                        'account_type'    => 'anyone',
                        //'account_name'    => 'not used',
                        'readGrant'       => true
                    )            
                ));
            } else {
                // add all grants to creator only
                $grants = new Tinebase_Record_RecordSet('Tinebase_Model_Grants', array(
                    array(
                        'account_id'     => $accountId,
                        'account_type'   => 'user',
                        //'account_name'   => 'not used',
                        'readGrant'      => true,
                        'addGrant'       => true,
                        'editGrant'      => true,
                        'deleteGrant'    => true,
                        'adminGrant'     => true
                    )            
                ));
            }
        } else {
            $grants = $_grants;
        }
        
        $this->setGrants($containerId, $grants, TRUE);
        
        return $container;
    }
    
    /**
     * add grants to container
     *
     * @todo check that grant is not already given to container/type/accout combi
     * @param int $_containerId
     * @param int $_accountId
     * @param array $_grants list of grants to add
     * @return boolean
     */
    public function addGrants($_containerId, $_accountType, $_accountId, array $_grants, $_ignoreAcl = FALSE)
    {
        $containerId = Tinebase_Model_Container::convertContainerIdToInt($_containerId);
        
        if($_ignoreAcl !== TRUE and !$this->hasGrant(Zend_Registry::get('currentAccount'), $_containerId, Tinebase_Model_Container::GRANT_ADMIN)) {
                throw new Exception('permission to manage grants on container denied');
        }
        
        switch($_accountType) {
            case 'user':
                $accountId = Tinebase_Model_User::convertUserIdToInt($_accountId);
                break;
            case 'group':
                $accountId = Tinebase_Model_Group::convertGroupIdToInt($_accountId);
                break;
            case 'anyone':
                $accountId = 0;
                break;
            default:
                throw new InvalidArgumentException('invalid $_accountType');
                break;
        }
        
        //$existingGrants = $this->getGrantsOfAccount($accountId, $containerId);
        $id = Tinebase_Record_Abstract::generateUID();
         
        foreach($_grants as $grant) {
            $data = array(
                'id'            => $id,
                'container_id'  => $containerId,
                'account_type'  => $_accountType,
                'account_id'    => $accountId,
                'account_grant' => $grant
            );
            $this->containerAclTable->insert($data);
        }

        $this->_removeFromCache($containerId);
        
        return true;
    }
    
    
    /**
     * return all container, which the user has the requested right for
     * - cache the results because this function is called very often
     *
     * used to get a list of all containers accesssible by the current user
     * 
     * @param int $_accountId
     * @param string $_application the application name
     * @param int $_grant the required grant
     * @return Tinebase_Record_RecordSet
     */
    public function getContainerByACL($_accountId, $_application, $_grant)
    {
        $accountId = Tinebase_Model_User::convertUserIdToInt($_accountId);

        $cache = Zend_Registry::get('cache');
        $cacheId = 'getContainerByACL' . $accountId . $_application . $_grant;
        $result = $cache->load($cacheId);
        
        if (!$result) {
            $groupMemberships   = Tinebase_Group::getInstance()->getGroupMemberships($accountId);
            if(count($groupMemberships) === 0) {
                throw new Exception('account must be in at least one group');
            }
            
            $applicationId = Tinebase_Application::getInstance()->getApplicationByName($_application)->getId();
                   
            $db = Zend_Registry::get('dbAdapter');
            
            $tableContainer = $db->quoteIdentifier(SQL_TABLE_PREFIX . 'container');
            $tableContainerAcl = $db->quoteIdentifier(SQL_TABLE_PREFIX . 'container_acl');
            $colId = $db->quoteIdentifier('id');
            #$colName = $db->quoteIdentifier('name');
            $colContainerId = $db->quoteIdentifier('container_id');
            $colApplicationId = $db->quoteIdentifier('application_id');
            $colAccountGrant = $db->quoteIdentifier('account_grant');
            $colAccountId = $db->quoteIdentifier('account_id');
            $colAccountType = $db->quoteIdentifier('account_type');
            
            $select = $db->select()
                ->from(SQL_TABLE_PREFIX . 'container')
                ->join(
                    SQL_TABLE_PREFIX . 'container_acl',
                    $tableContainer . '.' . $colId . ' = ' . $tableContainerAcl . '.' . $colContainerId , 
                    array()
                )
                ->where($tableContainer . '.' . $colApplicationId . ' = ?', $applicationId)
                ->where($tableContainerAcl . '.' . $colAccountGrant . ' = ?', $_grant)
                
                # beware of the extra parenthesis of the next 3 rows
                ->where('(' . $tableContainerAcl . '.' . $colAccountId . ' = ? AND ' . $tableContainerAcl . "." . $colAccountType . " ='user'", $accountId)
                ->orWhere($tableContainerAcl . '.' . $colAccountId . ' IN (?) AND ' . $tableContainerAcl . "." . $colAccountType . " ='group'", $groupMemberships)
                ->orWhere($tableContainerAcl . '.' . $colAccountType . ' = ?)', 'anyone')
                
                ->group(SQL_TABLE_PREFIX . 'container.id')
                ->order(SQL_TABLE_PREFIX . 'container.name');
           /* $select = $db->select()
                ->from(SQL_TABLE_PREFIX . 'container')
                ->join(
                    SQL_TABLE_PREFIX . 'container_acl',
                    SQL_TABLE_PREFIX . 'container.id = ' . SQL_TABLE_PREFIX . 'container_acl.container_id', 
                    array()
                )
                ->where(SQL_TABLE_PREFIX . 'container.application_id = ?', $applicationId)
                ->where(SQL_TABLE_PREFIX . 'container_acl.account_grant = ?', $_grant)
                
                # beware of the extra parenthesis of the next 3 rows
                ->where('(' . SQL_TABLE_PREFIX . 'container_acl.account_id = ? AND ' . SQL_TABLE_PREFIX . "container_acl.account_type ='user'", $accountId)
                ->orWhere(SQL_TABLE_PREFIX . 'container_acl.account_id IN (?) AND ' . SQL_TABLE_PREFIX . "container_acl.account_type ='group'", $groupMemberships)
                ->orWhere(SQL_TABLE_PREFIX . 'container_acl.account_type = ?)', 'anyone')
                
                ->group(SQL_TABLE_PREFIX . 'container.id')
                ->order(SQL_TABLE_PREFIX . 'container.name');
    */
            //error_log("getContainer:: " . $select->__toString());
    
            $stmt = $db->query($select);
            
            $rows = $stmt->fetchAll(Zend_Db::FETCH_ASSOC);
            
            if(empty($rows)) {
                // no containers found. maybe something went wrong when creating the initial folder
                // any account should have at least one personal folder
                // let's check if the controller of the application has a function to create the needed folders
                $application = Tinebase_Core::getApplicationInstance($_application);
                
                if($application instanceof Tinebase_Container_Interface) {
                    Zend_Registry::get('logger')->debug(__METHOD__ . '::' . __LINE__ . ' create personal folders for application ' . $_application);
                    return $application->createPersonalFolder($_accountId);
                }
            }
    
            $result = new Tinebase_Record_RecordSet('Tinebase_Model_Container', $rows);
            
            // save result and tag it with 'container'
            $cache->save($result, $cacheId, array('container'));
        }
            
        return $result;
        
    }
    
    /**
     * return a container by containerId
     * - cache the results because this function is called very often
     *
     * @todo move acl check to another place
     * @param int|Tinebase_Model_Container $_containerId the id of the container
     * @return Tinebase_Model_Container
     */
    public function getContainerById($_containerId)
    {
        $containerId = Tinebase_Model_Container::convertContainerIdToInt($_containerId);
        
        // load from cache
        $cache = Zend_Registry::get('cache');
        $result = $cache->load('getContainerById' . $containerId);

        if(!$result) {
                
            $row = $this->containerTable->find($containerId)->current();
            
            if($row === NULL) {
                throw new UnderflowException('container not found');
            }
            
            $result = new Tinebase_Model_Container($row->toArray());
            
            $cache->save($result, 'getContainerById' . $containerId);
        }
        
        return $result;
        
    }
    
    /**
     * return a container by container name
     *
     * @todo move acl check to another place
     * @param int|Tinebase_Model_Container $_containerId the id of the container
     * @return Tinebase_Model_Container
     */
    public function getContainerByName($_application, $_containerName, $_type)
    {
        if($_type !== Tinebase_Model_Container::TYPE_INTERNAL and $_type !== Tinebase_Model_Container::TYPE_PERSONAL and $_type !== Tinebase_Model_Container::TYPE_SHARED) {
            throw new Exception('invalid $_type supplied');
        }
        $applicationId = Tinebase_Application::getInstance()->getApplicationByName($_application)->getId();
        
        $colName = $this->containerTable->getAdapter()->quoteIdentifier('name');
        $colType = $this->containerTable->getAdapter()->quoteIdentifier('type');
        $colApplicationId = $this->containerTable->getAdapter()->quoteIdentifier('application_id');
        
        $select  = $this->containerTable->select()
            ->where($colName . ' = ?', $_containerName)
            ->where($colType . ' = ?', $_type)
            ->where($colApplicationId . ' = ?', $applicationId);

        $row = $this->containerTable->fetchRow($select);
        
        if($row === NULL) {
            throw new UnderflowException('container not found');
        }
        
        $result = new Tinebase_Model_Container($row->toArray());
        
        return $result;
        
    }
    
    /**
     * returns the internal conatainer for a given application
     *
     * @param int|Tinebase_Model_User $_accountId
     * @param string $_application name of the application
     * @return Tinebase_Model_Container the internal container
     */
    public function getInternalContainer($_accountId, $_application)
    {
        $applicationId = Tinebase_Application::getInstance()->getApplicationByName($_application)->getId();
        
        $select  = $this->containerTable->select()
            ->where('type = ?', Tinebase_Model_Container::TYPE_INTERNAL)
            ->where('application_id = ?', $applicationId);

        $row = $this->containerTable->fetchRow($select);
        
        if($row === NULL) {
            throw new UnderflowException('no internal container found');
        }

        $container = new Tinebase_Model_Container($row->toArray());
        
        if(!$this->hasGrant($_accountId, $container, Tinebase_Model_Container::GRANT_READ)) {
            throw new Exception('permission to container denied');
        }
        
        return $container;        
    }
    
    /**
     * returns the personal container of a given account accessible by a another given account
     *
     * @param int|Tinebase_Model_User $_accountId
     * @param string $_application
     * @param int|Tinebase_Model_User $_owner
     * @param int $_grant
     * @return Tinebase_Record_RecordSet of subtype Tinebase_Model_Container
     */
    public function getPersonalContainer($_accountId, $_application, $_owner, $_grant)
    {
        $accountId          = Tinebase_Model_User::convertUserIdToInt($_accountId);
        $groupMemberships   = Tinebase_Group::getInstance()->getGroupMemberships($accountId);
        if(count($groupMemberships) === 0) {
            throw new Exception('account must be in at least one group');
        }
        $ownerId            = Tinebase_Model_User::convertUserIdToInt($_owner);
        
        $db = Zend_Registry::get('dbAdapter');
        
        $application = Tinebase_Application::getInstance()->getApplicationByName($_application);

        $select = $db->select()
            ->from(array('owner' => SQL_TABLE_PREFIX . 'container_acl'), array())
            ->join(
                array('user' => SQL_TABLE_PREFIX . 'container_acl'),
                'owner.container_id = user.container_id', 
                array()
            )
            ->join(SQL_TABLE_PREFIX . 'container', 'owner.container_id = ' . SQL_TABLE_PREFIX . 'container.id')
            ->where('owner.account_id = ?', $ownerId)
            ->where('owner.account_grant = ?', Tinebase_Model_Container::GRANT_ADMIN)
            ->where('user.account_grant = ?', $_grant)

            # beware of the extra parenthesis of the next 3 rows
            ->where("(user.account_id = ? AND user.account_type ='user'", $accountId)
            ->orWhere("user.account_id IN (?) AND user.account_type ='group'", $groupMemberships)
            ->orWhere('user.account_type = ?)', 'anyone')
            
            ->where(SQL_TABLE_PREFIX . 'container.application_id = ?', $application->getId())
            ->where(SQL_TABLE_PREFIX . 'container.type = ?', Tinebase_Model_Container::TYPE_PERSONAL)
            ->group(SQL_TABLE_PREFIX . 'container.id')
            ->order(SQL_TABLE_PREFIX . 'container.name');
            
        //error_log("getContainer:: " . $select->__toString());

        $stmt = $db->query($select);
        
        $rows = $stmt->fetchAll(Zend_Db::FETCH_ASSOC);
        
        if(empty($rows) and $accountId === $ownerId) {
            // no containers found. maybe something went wrong when creating the initial folder
            // let's check if the controller of the application has a function to create the needed folders
            $application = Tinebase_Core::getApplicationInstance($application);
            
            if($application instanceof Tinebase_Container_Interface) {
                return $application->createPersonalFolder($accountId);
            }
        }

        $result = new Tinebase_Record_RecordSet('Tinebase_Model_Container', $rows);
        
        return $result;
    }
    
    /**
     * returns the shared container for a given application accessible by the current user
     *
     * @param int|Tinebase_Model_User $_accountId
     * @param string $_application the name of the application
     * @param int $_grant
     * @return Tinebase_Record_RecordSet set of Tinebase_Model_Container
     */
    public function getSharedContainer($_accountId, $_application, $_grant)
    {
        $accountId          = Tinebase_Model_User::convertUserIdToInt($_accountId);
        $groupMemberships   = Tinebase_Group::getInstance()->getGroupMemberships($accountId);
        
        if(count($groupMemberships) === 0) {
            throw new Exception('account must be in at least one group');
        }
        
        $db = Zend_Registry::get('dbAdapter');
        
        $application = Tinebase_Application::getInstance()->getApplicationByName($_application);

        $select = $db->select()
            ->from(SQL_TABLE_PREFIX . 'container_acl', array())
            ->join(SQL_TABLE_PREFIX . 'container', SQL_TABLE_PREFIX . 'container_acl.container_id = ' . SQL_TABLE_PREFIX . 'container.id')

            # beware of the extra parenthesis of the next 3 rows
            ->where('(' . SQL_TABLE_PREFIX . 'container_acl.account_id = ? AND ' . SQL_TABLE_PREFIX . "container_acl.account_type ='user'", $accountId)
            ->orWhere(SQL_TABLE_PREFIX . 'container_acl.account_id IN (?) AND ' . SQL_TABLE_PREFIX . "container_acl.account_type ='group'", $groupMemberships)
            ->orWhere(SQL_TABLE_PREFIX . 'container_acl.account_type = ?)', 'anyone')
            
            ->where(SQL_TABLE_PREFIX . 'container.application_id = ?', $application->getId())
            ->where(SQL_TABLE_PREFIX . 'container.type = ?', Tinebase_Model_Container::TYPE_SHARED)
            ->where(SQL_TABLE_PREFIX . 'container_acl.account_grant = ?', $_grant)
            ->group(SQL_TABLE_PREFIX . 'container.id')
            ->order(SQL_TABLE_PREFIX . 'container.name');
            
        //error_log("getContainer:: " . $select->__toString());

        $stmt = $db->query($select);

        $result = new Tinebase_Record_RecordSet('Tinebase_Model_Container', $stmt->fetchAll(Zend_Db::FETCH_ASSOC));
        
        return $result;
    }
    
    /**
     * return users which made personal containers accessible to current account
     *
     * @param int|Tinebase_Model_User $_accountId
     * @param string $_application the name of the application
     * @return Tinebase_Record_RecordSet set of Tinebase_Model_User
     */
    public function getOtherUsers($_accountId, $_application, $_grant)
    {
        $accountId          = Tinebase_Model_User::convertUserIdToInt($_accountId);
        $groupMemberships   = Tinebase_Group::getInstance()->getGroupMemberships($accountId);
        
        if(count($groupMemberships) === 0) {
            throw new Exception('account must be in at least one group');
        }
        
        $db = Zend_Registry::get('dbAdapter');
        
        $application = Tinebase_Application::getInstance()->getApplicationByName($_application);

        $select = $db->select()
            ->from(array('owner' => SQL_TABLE_PREFIX . 'container_acl'), array('account_id'))
            ->join(array('user' => SQL_TABLE_PREFIX . 'container_acl'),'owner.container_id = user.container_id', array())
            ->join(SQL_TABLE_PREFIX . 'container', 'user.container_id = ' . SQL_TABLE_PREFIX . 'container.id', array())
            ->where('owner.account_id != ?', $accountId)
            ->where('owner.account_grant = ?', Tinebase_Model_Container::GRANT_ADMIN)

            # beware of the extra parenthesis of the next 3 rows
            ->where("(user.account_id = ? AND user.account_type ='user'", $accountId)
            ->orWhere("user.account_id IN (?) AND user.account_type ='group'", $groupMemberships)
            ->orWhere('user.account_type = ?)', 'anyone')
            
            ->where('user.account_grant = ?', $_grant)
            ->where(SQL_TABLE_PREFIX . 'container.application_id = ?', $application->getId())
            ->where(SQL_TABLE_PREFIX . 'container.type = ?', Tinebase_Model_Container::TYPE_PERSONAL)
            ->order(SQL_TABLE_PREFIX . 'container.name')
            ->group('owner.account_id');
            
        //error_log("getContainer:: " . $select->__toString());

        $stmt = $db->query($select);
        $rows = $stmt->fetchAll(Zend_Db::FETCH_ASSOC);

        $result = new Tinebase_Record_RecordSet('Tinebase_Model_User');
        
        foreach($rows as $row) {
         try {
                $account = Tinebase_User::getInstance()->getUserById($row['account_id']);
                $result->addRecord($account);
            } catch (Exception $e) {
                // user does not exist any longer (hotfix)
            }
        }
        
        return $result;
    }
    
    /**
     * return set of all personal container of other users made accessible to the current account 
     *
     * @param int|Tinebase_Model_User $_accountId
     * @param string $_application the name of the application
     * @return Tinebase_Record_RecordSet set of Tinebase_Model_Container
     */
    public function getOtherUsersContainer($_accountId, $_application, $_grant)
    {
        $accountId          = Tinebase_Model_User::convertUserIdToInt($_accountId);
        $groupMemberships   = Tinebase_Group::getInstance()->getGroupMemberships($accountId);
        
        if(count($groupMemberships) === 0) {
            throw new Exception('account must be in at least one group');
        }
        
        $db = Zend_Registry::get('dbAdapter');
        
        $application = Tinebase_Application::getInstance()->getApplicationByName($_application);

        $select = $db->select()
            ->from(array('owner' => SQL_TABLE_PREFIX . 'container_acl'), array())
            ->join(
                array('user' => SQL_TABLE_PREFIX . 'container_acl'),
                'owner.container_id = user.container_id', 
                array())
            ->join(SQL_TABLE_PREFIX . 'container', 'user.container_id = ' . SQL_TABLE_PREFIX . 'container.id')
            ->where('owner.account_id != ?', $accountId)
            ->where('owner.account_grant = ?', Tinebase_Model_Container::GRANT_ADMIN)

            # beware of the extra parenthesis of the next 3 rows
            ->where("(user.account_id = ? AND user.account_type ='user'", $accountId)
            ->orWhere("user.account_id IN (?) AND user.account_type ='group'", $groupMemberships)
            ->orWhere('user.account_type = ?)', 'anyone')
            
            ->where('user.account_grant = ?', $_grant)
            ->where(SQL_TABLE_PREFIX . 'container.application_id = ?', $application->getId())
            ->where(SQL_TABLE_PREFIX . 'container.type = ?', Tinebase_Model_Container::TYPE_PERSONAL)
            ->group(SQL_TABLE_PREFIX . 'container.id')
            ->order(SQL_TABLE_PREFIX . 'container.name');
            
        //error_log("getContainer:: " . $select->__toString());

        $stmt = $db->query($select);

        $result = new Tinebase_Record_RecordSet('Tinebase_Model_Container', $stmt->fetchAll(Zend_Db::FETCH_ASSOC));
        
        return $result;
    }
    
    /**
     * delete container if user has the required right
     *
     * @param int|Tinebase_Model_Container $_containerId
     * @return void
     */
    public function deleteContainer($_containerId, $_ignoreAcl = FALSE)
    {
        $containerId = Tinebase_Model_Container::convertContainerIdToInt($_containerId);
        
        if($_ignoreAcl !== TRUE) {
            if(!$this->hasGrant(Zend_Registry::get('currentAccount'), $containerId, Tinebase_Model_Container::GRANT_ADMIN)) {
                throw new Exception('permission to delete container denied');
            }
            
            $container = $this->getContainerById($containerId);
            if($container->type !== Tinebase_Model_Container::TYPE_PERSONAL and $container->type !== Tinebase_Model_Container::TYPE_SHARED) {
                throw new Exception('can delete personal or shared containers only');
            }
        }        
        
        $where = array(
            $this->containerTable->getAdapter()->quoteInto('container_id = ?', $containerId)
        );
        $this->containerAclTable->delete($where);
        
        $where = array(
            $this->containerTable->getAdapter()->quoteInto('id = ?', $containerId)
        );
        $this->containerTable->delete($where);
        
        $this->_removeFromCache($containerId);
    }
    
    /**
     * set container name, if the user has the required right
     *
     * @param int $_containerId
     * @param string $_containerName the new name
     * @return Tinebase_Model_Container
     */
    public function setContainerName($_containerId, $_containerName)
    {
        $containerId = Tinebase_Model_Container::convertContainerIdToInt($_containerId);

        if(!$this->hasGrant(Zend_Registry::get('currentAccount'), $containerId, Tinebase_Model_Container::GRANT_ADMIN)) {
            throw new Exception('permission to rename container denied');
        }
        
        $where = array(
            $this->containerTable->getAdapter()->quoteInto('id = ?', $containerId)
        );
        
        $data = array(
            'name' => $_containerName
        );
        
        $this->containerTable->update($data, $where);

        $this->_removeFromCache($containerId);
        
        return $this->getContainerById($_containerId);
    }
    
    /**
     * check if the given user user has a certain grant
     *
     * @param int $_accountId
     * @param int|Tinebase_Model_Container $_containerId
     * @param int $_grant
     * @return boolean
     */
    public function hasGrant($_accountId, $_containerId, $_grant) 
    {
        $accountId = Tinebase_Model_User::convertUserIdToInt($_accountId);

        $containerId = Tinebase_Model_Container::convertContainerIdToInt($_containerId);
        
        $grant = (int)$_grant;
        if($grant != $_grant) {
            throw new InvalidArgumentException('$_grant must be integer');
        }
        
        $groupMemberships   = Tinebase_Group::getInstance()->getGroupMemberships($accountId);
        if(count($groupMemberships) === 0) {
            throw new Exception('account must be in at least one group');
        }
        
        $db = Zend_Registry::get('dbAdapter');

        $select = $db->select()
            ->from(SQL_TABLE_PREFIX . 'container_acl', array())
            ->join(SQL_TABLE_PREFIX . 'container', SQL_TABLE_PREFIX . 'container_acl.container_id = ' . SQL_TABLE_PREFIX . 'container.id', array('id'))

            # beware of the extra parenthesis of the next 3 rows
            ->where('(' . SQL_TABLE_PREFIX . 'container_acl.account_id = ? AND ' . SQL_TABLE_PREFIX . "container_acl.account_type ='user'", $accountId)
            ->orWhere(SQL_TABLE_PREFIX . 'container_acl.account_id IN (?) AND ' . SQL_TABLE_PREFIX . "container_acl.account_type ='group'", $groupMemberships)
            ->orWhere(SQL_TABLE_PREFIX . 'container_acl.account_type = ?)', 'anyone')
            
            ->where(SQL_TABLE_PREFIX . 'container_acl.account_grant = ?', $grant)
            ->where(SQL_TABLE_PREFIX . 'container.id = ?', $containerId);
                    
        //error_log("getContainer:: " . $select->__toString());

        $stmt = $db->query($select);
        
        $grants = $stmt->fetchAll(Zend_Db::FETCH_ASSOC);
        if(empty($grants)) {
            return FALSE;
        } else {
            return TRUE;
        }
    }
    
    /**
     * get all grants assigned to this container
     *
     * @param int|Tinebase_Model_Container $_containerId
     * @return Tinebase_Record_RecordSet subtype Tinebase_Model_Grants
     */
    public function getGrantsOfContainer($_containerId, $_ignoreAcl = FALSE) 
    {
        $containerId = Tinebase_Model_Container::convertContainerIdToInt($_containerId);
        
        if($_ignoreAcl !== TRUE) {
            if(!$this->hasGrant(Zend_Registry::get('currentAccount'), $containerId, Tinebase_Model_Container::GRANT_ADMIN)) {
                throw new Exception('permission to get grants of container denied');
            }            
        }
        
        $db = Zend_Registry::get('dbAdapter');
        
        $select = $db->select()
            ->from(SQL_TABLE_PREFIX . 'container', array('id'))
            ->join(SQL_TABLE_PREFIX . 'container_acl', SQL_TABLE_PREFIX . 'container_acl.container_id = ' . SQL_TABLE_PREFIX . 'container.id', 
                array('id', 'account_type', 'account_id', 'account_grants' => 'GROUP_CONCAT(' . SQL_TABLE_PREFIX . 'container_acl.account_grant)'))
            ->where(SQL_TABLE_PREFIX . 'container.id = ?', $containerId)
            ->group(array(SQL_TABLE_PREFIX . 'container.id', SQL_TABLE_PREFIX . 'container_acl.account_type', SQL_TABLE_PREFIX . 'container_acl.account_id'));

        //error_log("getAllGrants:: " . $select->__toString());

        $stmt = $db->query($select);

        $rows = $stmt->fetchAll(Zend_Db::FETCH_ASSOC);

        $result = new Tinebase_Record_RecordSet('Tinebase_Model_Grants');

        // @todo use _getGrantsFromArray here
        foreach($rows as $row) {
            $containerGrant = new Tinebase_Model_Grants($row);
            $grants = explode(',', $row['account_grants']);

            foreach($grants as $grant) {
                switch($grant) {
                    case Tinebase_Model_Container::GRANT_READ:
                        $containerGrant->readGrant = TRUE;
                        break;
                    case Tinebase_Model_Container::GRANT_ADD:
                        $containerGrant->addGrant = TRUE;
                        break;
                    case Tinebase_Model_Container::GRANT_EDIT:
                        $containerGrant->editGrant = TRUE;
                        break;
                    case Tinebase_Model_Container::GRANT_DELETE:
                        $containerGrant->deleteGrant = TRUE;
                        break;
                    case Tinebase_Model_Container::GRANT_ADMIN:
                        $containerGrant->adminGrant = TRUE;
                        break;
                }
            }

            $result->addRecord($containerGrant);
        }

        return $result;
    }
    
    /**
     * get grants assigned to one account of one container
     *
     * @param int|Tinebase_Model_User $_accountId the account to get the grants for
     * @param int|Tinebase_Model_Container $_containerId
     * @return Tinebase_Model_Grants
     */
    public function getGrantsOfAccount($_accountId, $_containerId, $_ignoreAcl = FALSE) 
    {
        $accountId          = Tinebase_Model_User::convertUserIdToInt($_accountId);
        $containerId        = Tinebase_Model_Container::convertContainerIdToInt($_containerId);
        
        $cacheKey = 'getGrantsOfAccount' . $containerId . $accountId . (int)$_ignoreAcl;
        // load from cache
        $cache = Zend_Registry::get('cache');
        $grants = $cache->load($cacheKey);

        if(!$grants) {
            $groupMemberships   = Tinebase_Group::getInstance()->getGroupMemberships($accountId);
            
            #if($_ignoreAcl !== TRUE) {
            #    if(!$this->hasGrant(Zend_Registry::get('currentAccount'), $containerId, Tinebase_Model_Container::GRANT_ADMIN)) {
            #        throw new Exception('permission to get grants of container denied');
            #    }            
            #}
            
            if(count($groupMemberships) === 0) {
                throw new Exception('account must be in at least one group');
            }
            
            $db = Zend_Registry::get('dbAdapter');
            
            $select = $db->select()
                ->from(SQL_TABLE_PREFIX . 'container_acl', array('account_grant'))
                ->join(SQL_TABLE_PREFIX . 'container', SQL_TABLE_PREFIX . 'container_acl.container_id = ' . SQL_TABLE_PREFIX . 'container.id')
    
                # beware of the extra parenthesis of the next 3 rows
                ->where('(' . SQL_TABLE_PREFIX . 'container_acl.account_id = ? AND ' . SQL_TABLE_PREFIX . "container_acl.account_type ='user'", $accountId)
                ->orWhere(SQL_TABLE_PREFIX . 'container_acl.account_id IN (?) AND ' . SQL_TABLE_PREFIX . "container_acl.account_type ='group'", $groupMemberships)
                ->orWhere(SQL_TABLE_PREFIX . 'container_acl.account_type = ?)', 'anyone')
    
                ->where(SQL_TABLE_PREFIX . 'container.id = ?', $containerId)
                ->group(SQL_TABLE_PREFIX . 'container_acl.account_grant');
    
            //error_log("getContainer:: " . $select->__toString());
    
            $stmt = $db->query($select);
    
            $rows = $stmt->fetchAll(Zend_Db::FETCH_ASSOC);
    
	        $grants = $this->_getGrantsFromArray($rows, $accountId);
            
            $cache->save($grants, $cacheKey);
        }
        return $grants;
    }
    
    /**
     * get grants assigned to multiple records
     *
     * @param Tinebase_Record_RecordSet $_records records to get the grants for
     * @param int|Tinebase_Model_User $_accountId the account to get the grants for
     * @param string $_containerProperty container property
     */
    public function getGrantsOfRecords(Tinebase_Record_RecordSet $_records, $_accountId, $_containerProperty = 'container_id')
    {
        if (count($_records) === 0) {
            return;
        }
        
        $accountId          = Tinebase_Model_User::convertUserIdToInt($_accountId);
        $groupMemberships   = Tinebase_Group::getInstance()->getGroupMemberships($accountId);
        
        if(count($groupMemberships) === 0) {
            throw new Exception('account must be in at least one group');
        }

        // get container ids
        $containers = array();
        foreach ($_records as $record) {
            if (!isset($containers[$record[$_containerProperty]])) {
                $containers[Tinebase_Model_Container::convertContainerIdToInt($record[$_containerProperty])] = array();
            }
        }
        
        //Zend_Registry::get('logger')->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($_records->toArray(), true));
        //Zend_Registry::get('logger')->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($containers, true));
        
        $db = Zend_Registry::get('dbAdapter');
        
        $select = $db->select()
            ->from(SQL_TABLE_PREFIX . 'container_acl', array('account_grants' => 'GROUP_CONCAT(' . SQL_TABLE_PREFIX . 'container_acl.account_grant)'))
            ->join(SQL_TABLE_PREFIX . 'container', SQL_TABLE_PREFIX . 'container_acl.container_id = ' . SQL_TABLE_PREFIX . 'container.id')

            # beware of the extra parenthesis of the next 3 rows
            ->where('(' . SQL_TABLE_PREFIX . 'container_acl.account_id = ? AND ' . SQL_TABLE_PREFIX . "container_acl.account_type ='user'", $accountId)
            ->orWhere(SQL_TABLE_PREFIX . 'container_acl.account_id IN (?) AND ' . SQL_TABLE_PREFIX . "container_acl.account_type ='group'", $groupMemberships)
            ->orWhere(SQL_TABLE_PREFIX . 'container_acl.account_type = ?)', 'anyone')

            ->where(SQL_TABLE_PREFIX . 'container.id IN (?)', array_keys($containers))
            ->group(SQL_TABLE_PREFIX . 'container.id');

        //Zend_Registry::get('logger')->debug(__METHOD__ . '::' . __LINE__ . ' ' . $select->__toString());

        $stmt = $db->query($select);

        $rows = $stmt->fetchAll(Zend_Db::FETCH_ASSOC);
        
        // add results to container ids and get grants array
        foreach ($rows as $row) {
            $grantsArray = array_unique(explode(',', $row['account_grants']));
            $row['account_grants'] = $this->_getGrantsFromArray($grantsArray, $accountId)->toArray();
            $containers[$row['id']] = $row;
        }

        //Zend_Registry::get('logger')->debug(__METHOD__ . '::' . __LINE__ . ' ' . print_r($containers, true));
        
        // add container & grants to records
        foreach ($_records as &$record) {
            $containerId = $record[$_containerProperty];
            $record[$_containerProperty] = $containers[$containerId];
        }
    }
    
    /**
     * set all grant for given container
     *
     * @param int|Tinebase_Model_Container $_containerId
     * @param Tinebase_Record_RecordSet $_grants
     * @return Tinebase_Record_RecordSet subtype Tinebase_Model_Grants
     */
    public function setGrants($_containerId, Tinebase_Record_RecordSet $_grants, $_ignoreAcl = FALSE) 
    {
        $containerId = Tinebase_Model_Container::convertContainerIdToInt($_containerId);
        
        if($_ignoreAcl !== TRUE) {
            if(!$this->hasGrant(Zend_Registry::get('currentAccount'), $containerId, Tinebase_Model_Container::GRANT_ADMIN)) {
                throw new Exception('permission to set grants of container denied');
            }            
        }
        
        $container = $this->getContainerById($containerId);
        
        # @todo find a new solution for this block
        
/*        if($container->type === Tinebase_Model_Container::TYPE_PERSONAL) {
            $currentAccountId = Zend_Registry::get('currentAccount')->getId();
            // make sure that only the current user has admin rights
            foreach($_grants as $key => $recordGrants) {
                $_grants[$key]->adminGrant = false;
            }
            
            if(isset($_grants[$currentAccountId])) {
                $_grants[$currentAccountId]->readGrant = true;
                $_grants[$currentAccountId]->addGrant = true;
                $_grants[$currentAccountId]->editGrant = true;
                $_grants[$currentAccountId]->deleteGrant = true;
                $_grants[$currentAccountId]->adminGrant = true;
            } else {
                $_grants[$currentAccountId] = new Tinebase_Model_Grants(
                    array(
                        'account_id'     => $currentAccountId,
                        'account_type'   => 'account',
                        //'account_name'   => 'not used',
                        'readGrant'      => true,
                        'addGrant'       => true,
                        'editGrant'      => true,
                        'deleteGrant'    => true,
                        'adminGrant'     => true
                    ), true);
            }
        } */
        
        //error_log(print_r($_grants->toArray(), true));
        
        try {
            $transactionId = Tinebase_TransactionManager::getInstance()->startTransaction(Zend_Registry::get('dbAdapter'));
            
            $where = $this->containerAclTable->getAdapter()->quoteInto('container_id = ?', $containerId);
            $this->containerAclTable->delete($where);
            
            foreach($_grants as $recordGrants) {
                $data = array(
                    'id'            => $recordGrants->getId(),
                    'container_id'  => $containerId,
                    'account_id'    => $recordGrants['account_id'],
                    'account_type'  => $recordGrants['account_type'],
                );
                if(empty($data['id'])) {
                    $data['id'] = $recordGrants->generateUID();
                }
                if($recordGrants->readGrant === true) {
                    $this->containerAclTable->insert($data + array('account_grant' => Tinebase_Model_Container::GRANT_READ));
                }
                if($recordGrants->addGrant === true) {
                    $this->containerAclTable->insert($data + array('account_grant' => Tinebase_Model_Container::GRANT_ADD));
                }
                if($recordGrants->editGrant === true) {
                    $this->containerAclTable->insert($data + array('account_grant' => Tinebase_Model_Container::GRANT_EDIT));
                }
                if($recordGrants->deleteGrant === true) {
                    $this->containerAclTable->insert($data + array('account_grant' => Tinebase_Model_Container::GRANT_DELETE));
                }
                if($recordGrants->adminGrant === true) {
                    $this->containerAclTable->insert($data + array('account_grant' => Tinebase_Model_Container::GRANT_ADMIN));
                }
            }
            
            Tinebase_TransactionManager::getInstance()->commitTransaction($transactionId);
            
            $this->_removeFromCache($containerId);
            
        } catch (Exception $e) {
            Tinebase_TransactionManager::getInstance()->rollBack();
            
            throw($e);
        }
        
        return $this->getGrantsOfContainer($containerId, $_ignoreAcl);
    }
    
    /**
     * remove container from cache if cache is available
     *
     * @param string $_cacheId
     * 
     * @todo memcached can't use tags -> clear complete cache or don't use tags in caching?
     */
    protected function _removeFromCache($_containerId) 
    {
        $accountId          = Tinebase_Model_User::convertUserIdToInt(Zend_Registry::get('currentAccount'));
        
        try {
            // remove container from cache
            $cache = Zend_Registry::get('cache');
            if (ucfirst(Zend_Registry::get('configFile')->caching->backend) !== 'Memcached') {
                $cache->remove('getContainerById' . $_containerId);
                $cache->remove('getGrantsOfAccount' . $_containerId . $accountId . 0);                
                $cache->remove('getGrantsOfAccount' . $_containerId . $accountId . 1);                
                $cache->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, array('container'));
            } else {
                $cache->clean(Zend_Cache::CLEANING_MODE_ALL);                
            }
        } catch (Exception $e) {
            // caching not configured
        }
    }

    /**
     * get grants record from an array with grant values
     *
     * @param array $_grantsArray
     * @param int $_accountId
     * @return Tinebase_Model_Grants
     */
    protected function _getGrantsFromArray(array $_grantsArray, $_accountId)
    {
        $grants = new Tinebase_Model_Grants( array(
            'account_id'     => $_accountId,
            'account_type'   => 'account',
        ));
        
        foreach($_grantsArray as $key => $value) {
            
            $grantValue = (is_array($value)) ? $value['account_grant'] : $value; 
            
            switch($grantValue) {
                case Tinebase_Model_Container::GRANT_READ:
                    $grants->readGrant = TRUE; 
                    break;
                case Tinebase_Model_Container::GRANT_ADD:
                    $grants->addGrant = TRUE; 
                    break;
                case Tinebase_Model_Container::GRANT_EDIT:
                    $grants->editGrant = TRUE; 
                    break;
                case Tinebase_Model_Container::GRANT_DELETE:
                    $grants->deleteGrant = TRUE; 
                    break;
                case Tinebase_Model_Container::GRANT_ADMIN:
                    $grants->adminGrant = TRUE; 
                    break;
            }
        }
        
        return $grants;
    }    
}