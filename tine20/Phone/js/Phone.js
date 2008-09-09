/**
 * Tine 2.0
 * 
 * @package     Phone
 * @license     http://www.gnu.org/licenses/agpl.html AGPL3
 * @author      Lars Kneschke <l.kneschke@metaways.de>
 * @copyright   Copyright (c) 2008 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id:Dialer.js 4159 2008-09-02 14:15:05Z p.schuele@metaways.de $
 *
 */
 
Ext.namespace('Tine.Phone');

/**************************** panel ****************************************/

/**
 * entry point, required by tinebase
 * creates and returnes app tree panel
 */
Tine.Phone.getPanel = function(){
	
    var translation = new Locale.Gettext();
    translation.textdomain('Phone');

    // @todo generalise this for panel & main
    var editPhoneSettingsAction = new Ext.Action({
        text: translation._('Edit phone settings'),
        iconCls: 'PhoneIconCls',
        handler: function() {
        	Tine.Tinebase.Common.openWindow('myPhonesWindow', 'index.php?method=Voipmanager.editMyPhone&phoneId=' + this.ctxNode.id, 700, 300);
        },
        scope: this
    });
    
    var contextMenu = new Ext.menu.Menu({
        items: [
            editPhoneSettingsAction
        ]    
    });
    
    /*********** tree panel *****************/

    var treePanel = new Ext.tree.TreePanel({
        title: 'Phone',
        id: 'phone-tree',
        iconCls: 'PhoneIconCls',
        rootVisible: true,
        border: false,
        collapsible: true
    });
    
    /*********** root node *****************/
    
    var treeRoot = new Ext.tree.TreeNode({
        text: translation._('Phones'),
        cls: 'treemain',
        allowDrag: false,
        allowDrop: true,
        id: 'root',
        icon: false
    });
    treePanel.setRootNode(treeRoot);
    
    Tine.Phone.loadPhoneStore();           
        
    /******** tree panel handlers ***********/
    
    treePanel.on('click', function(node){
    	// reload root node
    	if (node && node.id == 'root') {
    		Tine.Phone.Main.actions.editPhoneSettings.setDisabled(true);
    	}    	
        Tine.Phone.Main.show(node);
    }, this);
        
    treePanel.on('contextmenu', function(node, event){
        this.ctxNode = node;
        contextMenu.showAt(event.getXY());
    }, this);
        
    treePanel.on('beforeexpand', function(panel) {    	
    	// expand root (Phones) node
        if(panel.getSelectionModel().getSelectedNode() === null) {
            panel.expandPath('/root/all');
            panel.selectPath('/root/all');            
        }
        panel.fireEvent('click', panel.getSelectionModel().getSelectedNode());
        
        // @todo reload phone store ?
        //Tine.Phone.loadPhoneStore(true);
    }, this);

    treePanel.getSelectionModel().on('selectionchange', function(_selectionModel) {

    	var node = _selectionModel.getSelectedNode();

        // update toolbar
        var settingsButton = Ext.getCmp('phone-settings-button');
        if(node && node.id != 'root') {
        	settingsButton.setDisabled(false);
        } else {
            settingsButton.setDisabled(true);
        }
    }, this);
    
    return treePanel;
};

/**
 * load phones
 */
Tine.Phone.updatePhoneTree = function(store){
	
	//console.log('update tree');
	
    var translation = new Locale.Gettext();
    translation.textdomain('Phone');

    // get tree root
    var treeRoot = Ext.getCmp('phone-tree').getRootNode();    

	// remove all children first
    treeRoot.eachChild(function(child){
    	treeRoot.removeChild(child);
    });
	
    // add phones to tree menu
    store.each(function(record){
        //console.log(treeRoot);
        var label = (record.data.description == '') 
           ? record.data.macaddress 
           : Ext.util.Format.ellipsis(record.data.description, 30);
        var node = new Ext.tree.TreeNode({
            id: record.id,
            text: label,
            qtip: record.data.description,
            leaf: true
        });
        treeRoot.appendChild(node);
    });
    
    // expand root
    if (treeRoot.childNodes.length > 0) {
        treeRoot.expand();
    }    
    
    // don't call this again later
    //store.removeListener('load');
    store.purgeListeners();
};

/**************************** dialer form / function *******************************/
/**
 * dial function
 * - opens the dialer window if multiple phones/lines are available
 * - directly calls dial json function if number is set and just 1 phone and line are available
 * 
 * @param string phone number to dial
 * 
 * @todo use window factory later
 */
Tine.Phone.dialNumber = function(number) {
	
	var phonesStore = Tine.Phone.loadPhoneStore();
	var lines = phonesStore.getAt(0).data.lines;

    // check if only one phone / one line exists and numer is set
	if (phonesStore.getTotalCount() == 1 && lines.length == 1 && number) {
		// call Phone.dialNumber
        Ext.Ajax.request({
            url: 'index.php',
            params: {
                method: 'Phone.dialNumber',
                number: number,
                phoneId: phonesStore.getAt(0).id,
                lineId: lines[0].id 
            },
            success: function(_result, _request){
                // success
            },
            failure: function(result, request){
                // show error message?
            }
        });                

    } else {	

    	// open dialer box (with phone and lines selection)
        var dialerPanel = new Tine.Phone.DialerPanel({
            number: (number) ? number : null
        });           
        var dialer = new Ext.Window({
            //title: this.translation._('Dial phone number'),
            title: 'Dial phone number',
            id: 'dialerWindow',
            modal: true,
            width: 300,
            height: 150,
            layout: 'hfit',
            plain:true,
            bodyStyle:'padding:5px;',
            closeAction: 'close',
            items: [dialerPanel]        
        });
    
        dialer.show();          	
	}
};

/**
 * dialer form
 * 
 * @todo use macaddress or description/name as display value?
 */
Tine.Phone.DialerPanel = Ext.extend(Ext.form.FormPanel, {
	
	id: 'dialerPanel',
	translation: null,
	
	// initial phone number
	number: null,
	
	// config settings
    defaults: {
        xtype: 'textfield',
        anchor: '100%',
        allowBlank: false
    },	
	bodyStyle: 'padding:5px;',	
	buttonAlign: 'right',
	    
	phoneStore: null,
	linesStore: null,
	
    // private
    initComponent: function(){
        
        this.translation = new Locale.Gettext();
        this.translation.textdomain('Phone');    
        
        // set stores
        this.phoneStore = Tine.Phone.loadPhoneStore();
        this.setLineStore(this.phoneStore.getAt(0).id);
        
        /***************** form fields *****************/
        
        this.items = [
            {
                fieldLabel: this.translation._('Number'),
                name: 'phoneNumber',
            },{
                xtype: 'combo',
                fieldLabel: this.translation._('Phone'),
                store: this.phoneStore,
                mode: 'local',
                displayField:'macaddress',
                valueField: 'id',
                name: 'phoneId',
                tpl: new Ext.XTemplate(
                '<tpl for=".">' +
                    '<div class="x-combo-list-item">' +
                        '{[this.encode(values.macaddress, values.description)]}</tpl>' + 
                    '</div>' +
                '</tpl>',{
                    encode: function(mac, desc) {
                    	if (desc.length > 0) {
                            return Ext.util.Format.htmlEncode(Ext.util.Format.ellipsis(desc));
                    	} else {
                    		return Ext.util.Format.htmlEncode(mac);
                    	}
                    }
                })                
            },{
            	xtype: 'combo',
                fieldLabel: this.translation._('Line'),
                name: 'lineId',
                displayField:'linenumber',
                valueField: 'id',
                mode: 'local',
                store: this.linesStore
            }
        ];
        
        /******************* action buttons ********************/
        
        // cancel action
        this.cancelAction = new Ext.Action({   
            text: this.translation._('Cancel'),
            iconCls: 'action_cancel',
            handler : function(){
                Ext.getCmp('dialerWindow').close();
            }
        });
        	
        // dial action
		this.dialAction = new Ext.Action({
            scope: this,
            text: this.translation._('Dial'),
            iconCls: 'action_DialNumber',
            handler : function(){   
                var form = this.getForm();

                if (form.isValid()) {
                    Ext.Ajax.request({
                        url: 'index.php',
                        params: {
                            method: 'Phone.dialNumber',
                            number: form.findField('phoneNumber').getValue(),
                            phoneId: form.findField('phoneId').getValue(),
                            lineId: form.findField('lineId').getValue() 
                        },
                        success: function(_result, _request){
                            Ext.getCmp('dialerWindow').close();
                        },
                        failure: function(result, request){
                            // show error message?
                        }
                    });                
                }
            }
        });

        this.buttons = [
            this.cancelAction,
            this.dialAction
        ];
        
        /************** other initialisation ****************/
        
        this.initMyFields.defer(300, this);        

        Tine.Phone.DialerPanel.superclass.initComponent.call(this);        
    },
    
    /**
     * init form fields
     * 
     * @todo add prefered phone/line selections
     */
    initMyFields: function() {
    	// focus number field or set initial value
    	if (this.number != null) {
            this.getForm().findField('phoneNumber').setValue(this.number);
    	} else {
    		this.getForm().findField('phoneNumber').focus();
    	}

        // get combos
        var phoneCombo = this.getForm().findField('phoneId'); 
        var lineCombo = this.getForm().findField('lineId'); 
        
        // todo select first combo values
        phoneCombo.setValue(this.phoneStore.getAt(0).id);
        this.getForm().findField('lineId').setValue(this.linesStore.getAt(0).id);

        // reload lines combo on change
        phoneCombo.on('select', function(combo, newValue, oldValue){
        	this.setLineStore(newValue.data.id);
        	
        	// @todo remove this hack when the reload/update of the lines combo is working!
        	if (this.linesStore.getTotalCount > 1) {
            	this.getForm().findField('lineId').clearValue();
        	}
        }, this);
        
        // reset phone combo on expand
        phoneCombo.on('expand', function(combo){
        	combo.store.query('macaddress', '*');
        	combo.store.load();
        }, this);

        // @todo reset phone combo on expand to show the new lines
        /*
        lineCombo.on('expand', function(combo){
            combo.store.query('linenumber', '*');
            combo.store.fireEvent('datachanged');
            //combo.store.load();
            //combo.store.fireEvent('update');
        }, this);
        */
    },
    
    /**
     * get values from phones store
     */
    setLineStore: function(phoneId) {
    	
    	//console.log('set lines for ' + phoneId);
    	
    	if (this.linesStore == null) {
    	   this.linesStore = new Ext.data.Store({});
    	} else {
    		// empty store
    		this.linesStore.removeAll();
    	}
    	
    	var phone = this.phoneStore.getById(phoneId);
    	for(var i=0; i<phone.data.lines.length; i++) {
    		var lineRecord = new Tine.Voipmanager.Model.Snom.Line(phone.data.lines[i], phone.data.lines[i].id);
            this.linesStore.add(lineRecord);
    	}
    }
});

/**************************** main ****************************************/

// @todo add translations

Tine.Phone.Main = {
	actions: 
	{
	   	dialNumber: null,
	   	editPhoneSettings: null
	},
	
	initComponent: function()
    {
        this.actions.dialNumber = new Ext.Action({
            text: 'Dial number',
            tooltip: 'Initiate a new outgoing call',
            handler: this.handlers.dialNumber,
            iconCls: 'action_DialNumber',
            scope: this
        });
    	
        // @todo generalise this for panel & main
        this.actions.editPhoneSettings = new Ext.Action({
            id: 'phone-settings-button',
            //text: translation._('Edit phone settings'),
        	text: 'Edit phone settings',
            iconCls: 'PhoneIconCls',
            handler: function() {
            	// get selected node id
            	var node = Ext.getCmp('phone-tree').getSelectionModel().getSelectedNode();
            	
                Tine.Tinebase.Common.openWindow('myPhonesWindow', 'index.php?method=Voipmanager.editMyPhone&phoneId=' + node.id, 700, 300);
            },
            scope: this,
            disabled: true
        });
    },
    
    handlers: 
    {
    	dialNumber: function(_button, _event) {
    		Tine.Phone.dialNumber();
    	}
    },
 
    displayToolbar: function()
    {
        var quickSearchField = new Ext.ux.SearchField({
            id: 'quickSearchField',
            width:240,
            emptyText: 'enter searchfilter'
        }); 
        quickSearchField.on('change', function(){
            Ext.getCmp('Phone_Grid').getStore().load({
                params: {
                    start: 0,
                    limit: 50
                }
            });
        }, this);
        
        var toolbar = new Ext.Toolbar({
            id: 'Phone_Toolbar',
            split: false,
            height: 26,
            items: [
                this.actions.dialNumber, 
                this.actions.editPhoneSettings,
/*                '-',
                this.actions.exportContact,
                new Ext.Toolbar.MenuButton(this.actions.callContact),*/
                '->', 
                'Search:', 
                ' ',
                quickSearchField
            ]
        });

        Tine.Tinebase.MainScreen.setActiveToolbar(toolbar);
    },
    
    displayGrid: function() 
    {
        // the datastore
        var dataStore = new Ext.data.JsonStore({
            root: 'results',
            totalProperty: 'totalcount',
            id: 'id',
            fields: Tine.Addressbook.Model.Contact,
            // turn on remote sorting
            remoteSort: true
        });
        
        dataStore.setDefaultSort('n_family', 'asc');

        dataStore.on('beforeload', function(_dataStore) {
            _dataStore.baseParams.filter = Ext.getCmp('quickSearchField').getRawValue();
        }, this);   
        
        //Ext.StoreMgr.add('ContactsStore', dataStore);
        
        // the paging toolbar
        var pagingToolbar = new Ext.PagingToolbar({
            pageSize: 25,
            store: dataStore,
            displayInfo: true,
            displayMsg: 'Displaying contacts {0} - {1} of {2}',
            emptyMsg: "No contacts to display"
        }); 
        
        // the columnmodel
        var columnModel = new Ext.grid.ColumnModel([
            { resizable: true, id: 'n_family', header: 'Family name', dataIndex: 'n_family' },
            { resizable: true, id: 'n_given', header: 'Given name', dataIndex: 'n_given', width: 80 },
            { resizable: true, id: 'n_fn', header: 'Full name', dataIndex: 'n_fn', hidden: true },
            { resizable: true, id: 'n_fileas', header: 'Name + Firm', dataIndex: 'n_fileas', hidden: true }
        ]);
        
        columnModel.defaultSortable = true; // by default columns are sortable
        
        // the rowselection model
        var rowSelectionModel = new Ext.grid.RowSelectionModel({multiSelect:true});

        rowSelectionModel.on('selectionchange', function(_selectionModel) {
            var rowCount = _selectionModel.getCount();

            if(rowCount < 1) {
                // no row selected
                this.actions.deleteContact.setDisabled(true);
                this.actions.editContact.setDisabled(true);
                this.actions.exportContact.setDisabled(true);
                this.actions.callContact.setDisabled(true);
            } else if(rowCount > 1) {
                // more than one row selected
                this.actions.deleteContact.setDisabled(false);
                this.actions.editContact.setDisabled(true);
                this.actions.exportContact.setDisabled(true);
                this.actions.callContact.setDisabled(true);
            } else {
                // only one row selected
                this.actions.deleteContact.setDisabled(false);
                this.actions.editContact.setDisabled(false);
                this.actions.exportContact.setDisabled(false);

                var callMenu = Ext.menu.MenuMgr.get('Addressbook_Contacts_CallContact_Menu');
                callMenu.removeAll();
                var contact = _selectionModel.getSelected();
                if(!Ext.isEmpty(contact.data.tel_work)) {
                    callMenu.add({
                       id: 'Addressbook_Contacts_CallContact_Work', 
                       text: 'work ' + contact.data.tel_work + '',
                       handler: this.handlers.callContact
                    });
                    this.actions.callContact.setDisabled(false);
                }
                if(!Ext.isEmpty(contact.data.tel_home)) {
                    callMenu.add({
                       id: 'Addressbook_Contacts_CallContact_Home', 
                       text: 'home ' + contact.data.tel_home + '',
                       handler: this.handlers.callContact
                    });
                    this.actions.callContact.setDisabled(false);
                }
                if(!Ext.isEmpty(contact.data.tel_cell)) {
                    callMenu.add({
                       id: 'Addressbook_Contacts_CallContact_Cell', 
                       text: 'cell ' + contact.data.tel_cell + '',
                       handler: this.handlers.callContact
                    });
                    this.actions.callContact.setDisabled(false);
                }
                if(!Ext.isEmpty(contact.data.tel_cell_private)) {
                    callMenu.add({
                       id: 'Addressbook_Contacts_CallContact_CellPrivate', 
                       text: 'cell private ' + contact.data.tel_cell_private + '',
                       handler: this.handlers.callContact
                    });
                    this.actions.callContact.setDisabled(false);
                }
            }
        }, this);
        
        // the gridpanel
        var gridPanel = new Ext.grid.GridPanel({
            id: 'Addressbook_Contacts_Grid',
            store: dataStore,
            cm: columnModel,
            tbar: pagingToolbar,     
            autoSizeColumns: false,
            selModel: rowSelectionModel,
            enableColLock:false,
            loadMask: true,
            autoExpandColumn: 'n_family',
            border: false,
            view: new Ext.grid.GridView({
                autoFill: true,
                forceFit:true,
                ignoreAdd: true,
                emptyText: 'No contacts to display'
            })            
            
        });
        
        gridPanel.on('rowcontextmenu', function(_grid, _rowIndex, _eventObject) {
            _eventObject.stopEvent();
            if(!_grid.getSelectionModel().isSelected(_rowIndex)) {
                _grid.getSelectionModel().selectRow(_rowIndex);
            }
            var contextMenu = new Ext.menu.Menu({
                id:'ctxMenuContacts', 
                items: [
                    this.actions.editContact,
                    this.actions.deleteContact,
                    this.actions.exportContact,
                    '-',
                    this.actions.addContact 
                ]
            });
            contextMenu.showAt(_eventObject.getXY());
        }, this);
        
        gridPanel.on('rowdblclick', function(_gridPar, _rowIndexPar, ePar) {
            var record = _gridPar.getStore().getAt(_rowIndexPar);
            //console.log('id: ' + record.data.id);
            try {
                Tine.Tinebase.Common.openWindow('contactWindow', 'index.php?method=Addressbook.editContact&_contactId=' + record.data.id, 800, 600);
            } catch(e) {
                // alert(e);
            }
        }, this);

        // add the grid to the layout
        Tine.Tinebase.MainScreen.setActiveContentPanel(gridPanel);
    },

    updateMainToolbar : function() 
    {
        var menu = Ext.menu.MenuMgr.get('Tinebase_System_AdminMenu');
        menu.removeAll();
        /*menu.add(
            {text: 'product', handler: Tine.Crm.Main.handlers.editProductSource}
        );*/

        var adminButton = Ext.getCmp('tineMenu').items.get('Tinebase_System_AdminButton');
        adminButton.setIconClass('PhoneTreePanel');
        //if(Tine.Addressbook.rights.indexOf('admin') > -1) {
        //    adminButton.setDisabled(false);
        //} else {
            adminButton.setDisabled(true);
        //}

        var preferencesButton = Ext.getCmp('tineMenu').items.get('Tinebase_System_PreferencesButton');
        preferencesButton.setIconClass('PhoneTreePanel');
        preferencesButton.setDisabled(true);
    },
    
	show: function(_mode) 
	{	
        this.initComponent();
        
        var currentToolbar = Tine.Tinebase.MainScreen.getActiveToolbar();

        if(currentToolbar === false || currentToolbar.id != 'Phone_Toolbar') {
            this.displayToolbar();
            // removed the grid for the moment because we can't get the phone calls yet
            // @todo add grid again
            //this.displayGrid();
            Tine.Tinebase.MainScreen.setActiveContentPanel(new Ext.Panel({}));
            this.updateMainToolbar();
        }
        //this.loadData(_node);		
	}
};

/**************************** store ****************************************/

/**
 * get user phones store
 *
 * @return Ext.data.JsonStore with phones
 */
Tine.Phone.loadPhoneStore = function(reload) {
	
	//console.log('get store');
	
    var store = Ext.StoreMgr.get('UserPhonesStore');
    
    if (!store) {
        // create store
        store = new Ext.data.JsonStore({
            fields: Tine.Voipmanager.Model.Snom.Phone,
            baseParams: {
                method: 'Phone.getUserPhones',
                accountId: Tine.Tinebase.Registry.get('currentAccount').accountId
            },
            root: 'results',
            totalProperty: 'totalcount',
            id: 'id',
            remoteSort: false
        });
        
        Ext.StoreMgr.add('UserPhonesStore', store);
        
        store.on('load', Tine.Phone.updatePhoneTree, this);
        
        store.load();
        
    } else if (reload == true) {
    	
    	store.on('load', Tine.Phone.updatePhoneTree, this);
    	
    	store.load();
    }
    
    
    return store;
};
