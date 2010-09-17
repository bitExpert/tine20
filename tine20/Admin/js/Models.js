/*
 * Tine 2.0
 * 
 * @package     Admin
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Philipp Schuele <p.schuele@metaways.de>
 * @copyright   Copyright (c) 2009-2010 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id: Models.js 10409 2009-09-11 12:23:23Z p.schuele@metaways.de $
 *
 */
 
Ext.ns('Tine.Admin.Model');

/**
 * @namespace   Tine.Admin.Model
 * @class       Tine.Admin.Model.TagRight
 * @extends     Ext.data.Record
 * 
 * TagRight Record Definition
 */ 
Tine.Admin.Model.TagRight = Ext.data.Record.create([
    {name: 'account_id'},
    {name: 'account_type'},
    {name: 'account_name'},
    {name: 'account_data'},
    {name: 'view_right', type: 'boolean'},
    {name: 'use_right',  type: 'boolean'}
]);

/**
 * @namespace   Tine.Admin.Model
 * @class       Tine.Admin.Model.AccessLog
 * @extends     Tine.Tinebase.data.Record
 * 
 * TagRight Record Definition
 */ 
Tine.Admin.Model.AccessLog = Tine.Tinebase.data.Record.create([
    {name: 'sessionid'},
    {name: 'login_name'},
    {name: 'ip'},
    {name: 'li', type: 'date', dateFormat: Date.patterns.ISO8601Long},
    {name: 'lo', type: 'date', dateFormat: Date.patterns.ISO8601Long},
    {name: 'id'},
    {name: 'account_id'},
    {name: 'result'}
], {
    appName: 'Admin',
    modelName: 'AccessLog',
    idProperty: 'id',
    titleProperty: 'login_name',
    // ngettext('AccessLog', 'AccessLogs', n);
    recordName: 'AccessLog',
    recordsName: 'AccessLogs'
});

Tine.Admin.accessLogBackend = new Tine.Tinebase.data.RecordProxy({
    appName: 'Admin',
    modelName: 'AccessLog',
    recordClass: Tine.Admin.Model.AccessLog,
    idProperty: 'id'
});

