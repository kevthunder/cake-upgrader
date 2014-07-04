<?php
class Upgrader extends Object {
	/*
		App::import('Lib', 'Upgrader.Upgrader');
	*/
	var $testsOpt = array(
		'schemaAdd' => array(
			//'break' => true,
			'recheck' => true,
		),
		'schemaUpdate' => array(
			'dropConstraint' => array(),
			'recheck' => true,
		),
		/*'htmlLongText' => array(
			'fields'=> array(
				'newsletters' => 'html',
				'newsletter_variants' => 'html',
				'newsletter_sendings' => 'html',
			)
		),*/
		'langViews' => array(
			'optional' => true,
		),
	);
	var $defOpt = array(
		'check'=>null,
		'fix'=>null,
		'recheck' => false,
		'break' => false,
		'optional' => false,
		'multiple' => false,
	);
	
	var $name = null;
	var $config = null;
	
	public static function requireUpgraded($name,$controller) {
		
		
		if($controller->params['plugin'] != 'upgrader'){
			$upgrader = new Upgrader($name);
			if($upgrader->validable() && $upgrader->check()) {
				$controller->redirect(array('plugin'=>'upgrader','controller'=>'upgrader','action'=>'upgrade',$name,'admin'=>true));
			}
		}
	}
	
	function __construct($name){
		$this->name = $name;
		App::import('Lib', 'Upgrader.ClassCollection'); 
		$this->config = ClassCollection::getObject('upgrader_config',$name);
		$this->config->init($this);
		
		$this->tests = $this->testsOpt;
	}
	
	function addTest($name,$opt,$before = null){
		if(isset($this->testsOpt[$name])){
			$this->testsOpt[$name] = array_merge($this->testsOpt[$name],$opt);
		}elseif(empty($before) || !isset($this->testsOpt[$before])){
			$this->testsOpt[$name] = $opt;
		}else{
			$pos = array_search($before, array_keys($this->testsOpt));
			$this->testsOpt = array_merge
			(
				array_slice($this->testsOpt, 0, $pos),
				array($name => $opt),
				array_slice($this->testsOpt, $pos, null)
			);
		}
	}
	
	function validable(){
		if(empty($this->config)) return false;
		if($this->config->allwaysValidable) return true;
		
		App::import('Model', 'CakeSchema', false);
		
		$Schema =& new CakeSchema($this->schemaOpt());
		$file = $Schema->path . DS . $Schema->file;
		return file_exists($file);
	}
	
	function cache_name(){
		return Inflector::underscore($this->config->name).'_upgraded';
	}
	
	function check($optionals = false){
		if(!$this->validable()){
			throw new Exception('There is an error in the Updater config.');
		}
	
		$cached = Cache::read($this->cache_name());
		if( Configure::read('debug') < 1 && $cached){//heavyCache
			return false;
		}
		
		$schemaSha1 = $this->getSchemaSha1();
		if($schemaSha1 == $cached){
			return false;
		}
		
		$this->errors = array();
		foreach($this->tests as $test => &$opt){
			$opt = array_merge($this->defOpt,$opt);
			
			if($opt['optional'] && !$optionals) continue;
			
			if(!isset($opt['check'])){
				if(method_exists('Upgrader','check_'.$test)){
					$opt['check'] = 'check_'.$test;
				}elseif($opt['optional'] && method_exists('Upgrader','fix_'.$test)){
					$opt['check'] = true;
				}else{
					$opt['check'] = false;
				}
			}
			if($opt['check']){
				if($opt['check'] === true){
					$res = true;
				}else{
					$res = $this->{$opt['check']}($opt);
				}
				if($res) {
					$this->errors[$test] = $res;
					if($opt['recheck']){
						break;
					}
				}
			}
		}
		if(!empty($this->errors)){
			return $this->errors;
		}
		$this->_clearCaches();
		
		Cache::write($this->cache_name(), $schemaSha1?$schemaSha1:1);
		
		return false;
	}
	
	function run(&$msgs){
		$db =& $this->getDb();
		$db->cacheSources = false;
		
		$this->check(true);
		while(!empty($this->errors)){
			foreach($this->errors as $test => $error){
				$opt = &$this->tests[$test];
				if(!isset($opt['fix'])){
					if(method_exists('Upgrader','fix_'.$test)){
						$opt['fix'] = 'fix_'.$test;
					}else{
						$opt['fix'] = false;
					}
				}
				if($opt['fix'] && (!$opt['fixed'] || $opt['multiple'])){
					$msg = array();
					$res = $this->{$opt['fix']}($error,$opt,$msg);
					if(!$res){
						if(empty($msg)) $msg = '"'.$test.'" fix failled';
						$msgs = array_merge($msgs,(array)$msg);
						return false;
					}
					$opt['fixed'] = true;
					if($opt['break']){
						$this->_clearCaches();
						return 'break';
					}
					if($opt['recheck']){
						$this->_clearCaches();
						$this->check(true);
						continue 2;
					}
				}
			}
			$this->errors = array();
		}
		
		
		return true;
	}
	
	///////////////////////////////////////////////////////////////////////
	/////////////////////////// util functions ///////////////////////////
	///////////////////////////////////////////////////////////////////////
	
	function schemaOpt(){
		$def = array('name'=>$this->config->name, 'file'=>'schema.php', 'connection'=>$this->config->connection, 'plugin'=>$this->config->plugin);
		return array_merge($def,$this->config->schemaOpt);
	}
	
	function getDb(){
		App::import('Lib', 'ConnectionManager');
		return ConnectionManager::getDataSource($this->config->connection);
	}
	
	function getSchema(){
		if(empty($this->Schema)){
			App::import('Model', 'CakeSchema', false);
			$this->Schema =& new CakeSchema($this->schemaOpt());
			$this->Schema = $this->Schema->load();
		}
		return $this->Schema;
	}
	
	function getSchemaSha1(){

		App::import('Model', 'CakeSchema', false);
		$Schema =& new CakeSchema($this->schemaOpt());
		$file = $Schema->path . DS . $Schema->file;
		
		return sha1(sha1_file($file).serialize($this->testsOpt));
	}
	
	function getSourceList(){
		if(empty($this->sourceList)){
			$db =& $this->getDb();
			
			$this->sourceList = $db->listSources();
		}
		return $this->sourceList;
	}
	
	function getSchemaDiff(){
		if(empty($this->schemaDiff)){
			$Schema = $this->getSchema();
			$SchemaCurrent =& new CakeSchema($this->schemaOpt());
			$SchemaCurrent =$this->Schema->read(array('models'=>false));
			//debug($Schema);
			//debug($SchemaCurrent);
			if(version_compare(Configure::version(), '1.3.6', '>=')){
				$this->schemaDiff = $Schema->compare($SchemaCurrent);
			}else{
				$this->schemaDiff = @$Schema->compare($SchemaCurrent);
			}
		}
		return $this->schemaDiff;
	}
	
	function _clearCaches(){
		clearCache(null, 'models');
		ClassRegistry::flush( );
		$this->schemaDiff = null;
		$this->sourceList = null;
	}
	///////////////////////////////////////////////////////////////////////
	/////////////////////////// CHECK functions ///////////////////////////
	///////////////////////////////////////////////////////////////////////
	
	function check_schemaAdd(){
		
		$sourceList = $this->getSourceList();
		$Schema = $this->getSchema();
		$tables = array_diff(array_keys($Schema->tables),$sourceList);
		if(!empty($tables)) return $tables;
		
		return false;
	}
	
	function check_schemaUpdate($opt){
		
		$diff = $this->getSchemaDiff();
		
		if(!empty($diff)){
			$edit = array();
			foreach($diff as $table => $modif){
				if(!empty($modif['drop'])){
					if(!empty($opt['dropConstraint'][$table]['only'])){
						$modif['drop'] = array_intersect_key($modif['drop'],array_flip((array)$opt['dropConstraint'][$table]['only']));
					}
					if(empty($modif['drop'])) unset($modif['drop']);
				}
				if(!empty($modif)) $edit[$table] = $modif;
			}
			if(!empty($edit)){
				return $edit;
			}
		}
		return false;
	}

	/*function check_htmlLongText($opt){
		
		$db =& $this->getDb();
		
		$mismatch = array();
		foreach ($opt['fields'] as $table=>$field) {
			$cols = $db->query('DESCRIBE `'.$table.'`');
			
			$htmlField = null;
			foreach($cols as $col){
				$colKey = key($col);
				if($col[$colKey]['Field'] == $field){
					$htmlField = $col[$colKey];
					break;
				}
			}
			if($htmlField['Type'] != 'longtext'){
				$mismatch[$table] = $field;
			}
		}
		
		if(!empty($mismatch)) return $mismatch;
		return false;
	}*/
	
	
	function check_langViews($opt){
		$db =& $this->getDb();
		
		$langs = array('eng', 'fre');
		$patern = '/^(.*)_('.implode('|',$langs).')$/';
		$tables = array_keys($this->getSchema()->tables);
		$traducted = array();
		
		foreach($tables as $table){
			$cols = $db->query('DESCRIBE `'.$table.'`');
			
			foreach($langs as $lang){
				$traducted_cols = $aliases = array();
				foreach($cols as $col){
					$col = $col[key($col)];
					if(preg_match($patern,$col['Field'],$matches)){
						if($matches[2] == $lang){
							$traducted_cols[] = $col['Field'];
							$aliases[$col['Field']] = $matches[1];
						}elseif($matches[1] == 'title'){
							$aliases[$col['Field']] = $matches[1].'_t';
						}
					}else{
						$aliases[$col['Field']] = $col['Field'];
					}
				}
				if(!empty($traducted_cols)){
					$traducted[$table][$lang] = $aliases;
				}
			}
		}
		if(!empty($traducted)) return $traducted;
		return false;
	}
	/////////////////////////////////////////////////////////////////////
	/////////////////////////// FIX functions ///////////////////////////
	/////////////////////////////////////////////////////////////////////
	
	function fix_schemaAdd($error,$opt,&$msg){
		
		$Schema = $this->getSchema();
		$db =& $this->getDb();
		foreach($error as $table){
			$query = $db->createSchema($Schema,$table);
			//debug($query);
			if(!$db->execute($query)){
				$msg = str_replace('%table%',$table,__('Failled to create table `%table%` :',true)).' '.$query;
				return false;
			}
		}
		return true;
	}
	
	function fix_schemaUpdate($error,$opt,&$msg){
		$db =& $this->getDb();
		$queries = array();
		
		foreach($error as $table => $modif){
			$queries[] = $db->alterSchema(array($table => $modif),$table);
		}
		
		//debug($queries);
		foreach($queries as $query){
			if(!$db->execute($query)){
				$msg[] = __('Unable to execute query :',true).' '.$query;
				return false;
				break;
			}
		}
		
		return true;
	}
	
	/*function fix_htmlLongText($error,$opt,&$msg){
		$db =& $this->getDb();
		
		foreach ($error as $table=>$field) {
			$query = 'ALTER TABLE  `'.$table.'` CHANGE  `'.$field.'`  `'.$field.'` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;';
			//debug($query);
			if(!$db->execute($query)){
				$msg = __('Unable to execute query :',true).' '.$query;
				return false;
			}
		}
		return true;
	}*/
	
	function fix_langViews($error,$opt,&$msg){
		$db =& $this->getDb();
		
		foreach($error as $table => $views){
			foreach($views as $lang => $fields){
				$queries[] = 'DROP VIEW IF EXISTS `'.$table.'_'.$lang.'`;';
				$queries[] = 'CREATE VIEW `'.$table.'_'.$lang.'` (`'.join('`, `', array_values($fields)).'`) AS SELECT `'.join('`, `', array_keys($fields)).'` FROM `'.$table.'`;';
			}
		}
		
		foreach($queries as $query){
			if(!$db->execute($query)){
				$msg[] = __('Unable to execute query :',true).' '.$query;
				return false;
				break;
			}
		}
		
		return true;
	}
}
?>