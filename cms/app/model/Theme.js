Ext.define('Cetera.model.Theme', {
    extend: 'Ext.data.Model',
    fields: [
		'name', 
        'title', 
        'description', 
        'author',
        'developerMode',
		'disableUpgrade',
		'version',
		{name:'cms_version_min', persist: false},
		{name:'cms_version_min', persist: false},
		{name:'cms_version_max', persist: false},		
		{name:'compatible', persist: false},
		{name:'compatible_message', persist: false},
		{name:'upgrade', persist: false},
		{name:'repository', persist: false},
		{name:'installed', persist: false},
		{name:'general', persist: false}
    ],

    proxy: {
		type: 'ajax',
		simpleSortMode: true,
        api: {
            read    : 'include/data_themes.php',
            update  : 'include/data_themes.php?action=update',			
        },		
        reader: {
			type: 'json',
            root: 'rows'
        }
    }		
});