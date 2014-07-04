<?php
class UpgraderConfig extends Object {

	var $connection = 'default';
	var $schemaOpt = array();
	var $testsOpt = array();
	//var $schemaUpdateOpt = array();
	var $allwaysValidable = false;
	
	function init($upgrader){
		$this->mergeTests($upgrader);
		if(get_class($this) == 'UpgraderConfig'){
			$this->setDefaultPlugin($upgrader);
		}
	}
	
	function mergeTests($upgrader){
		foreach($this->testsOpt as $name => $opt){
			$upgrader->addTest($name,$opt,isset($opt['before'])?$opt['before']:null);
		}
		//$upgrader->testsOpt['schemaUpdate'] = array_merge($upgrader->testsOpt['schemaUpdate'],$this->schemaUpdateOpt);
	}
	
	function setDefaultPlugin($upgrader){
		
		App::import('Model', 'CakeSchema', false);
		
		$Schema =& new CakeSchema($upgrader->schemaOpt());
		$file = $Schema->path . DS . $Schema->file;
		if(!file_exists($file)){
			$this->plugin = $this->name;
			
			$Schema =& new CakeSchema($upgrader->schemaOpt());
			$file = $Schema->path . DS . $Schema->file;
			if(!file_exists($file)){
				$this->plugin = null;
			}
		}
	}
}