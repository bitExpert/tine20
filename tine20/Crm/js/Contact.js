/**
 * Tine 2.0
 * contacts combo box and store
 * 
 * @package     Crm
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2007-2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id$
 *
 */

Ext.namespace('Tine.Crm', 'Tine.Crm.Contact');

/**
 * contact selection combo box
 * 
 * @class Tine.Crm.Contact.ComboBox
 * @extends Tine.Addressbook.SearchCombo
 */
Tine.Crm.Contact.ComboBox = Ext.extend(Tine.Addressbook.SearchCombo, {

    //private
    initComponent: function(){
        this.contactFields = Tine.Addressbook.Model.ContactArray;
        this.contactFields.push({name: 'relation'});   // the relation object           
        this.contactFields.push({name: 'relation_type'});
        
        Tine.Crm.Contact.ComboBox.superclass.initComponent.call(this);        
    },
    
    /**
     * override default onSelect
     * 
     * TODO add the ContactsStore to the combo box config?
     */
    onSelect: function(record){  
        record.data.relation_type = 'customer';            
        var store = Ext.StoreMgr.lookup('ContactsStore');
        
        // check if already in
        if (!store.getById(record.id)) {
            store.add([record]);
        }
            
        this.collapse();
        this.clearValue();
    }    
});
