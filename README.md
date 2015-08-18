# Database Migration Plugin

Pour cakePHP 1.3

This plugin offer a way to check if the database is up to date and run the appropriate corrections. All this from the web site Admin.

## Installation

* put the content of this plugin in "app/plugins/" in a folder named "migration".

## Getting started

In the plugin you wish to be upgradadable add this to it's AppController 

```php
function constructClasses() {
	if(in_array('Upgrader',App::objects('plugin')) && !empty($this->params['admin'])) {
		App::import('Lib', 'Upgrader.Upgrader');
		Upgrader::requireUpgraded('Shop',$this);
	}
		
	return parent::constructClasses();
}
```

The basic Upgrade you can create is to update the shema

With this command you will create a file (config/schema/schema.php) containing all the field of the tables used by your plugin. The file will be automaticaly detected and ask to update the database if the shema change.

```sh
php cake\console\cake.php schema generate -plugin (PluginName)
```

To add more update tasks, Create the file `config/plugin_name_update.php`

```php
<?php
class PluginNameUpgrade extends UpgraderConfig {
	var $testsOpt = array();
}
```


## todo

- extract the types definition from the ClassCollection Class
- Class for each task