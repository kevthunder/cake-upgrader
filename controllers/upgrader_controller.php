<?php
class UpgraderController extends UpgraderAppController {

	var $name = 'Upgrader';
	var $helpers = array('Html', 'Form');
	var $components = array();
	var $uses = array();

	function admin_upgrade($name = null){
		if(empty($name)){
			$this->Session->setFlash(__d('upgrader','Missing config name', true));
			$this->redirect('/admin');
		}
		clearCache(null, 'models');
		App::import('Lib', 'Upgrader.Upgrader');
		$upgrader = new Upgrader($name);
		$sErrors = $upgrader->check(true);
		if(!$sErrors){
			$this->Session->setFlash(__d('upgrader','The database is valid', true));
			//$this->redirect(array('plugin'=>'upgrader','controller'=>'upgrader','action'=>'index'));
			$this->redirect('/admin');
		}
		$this->set('name',$name);
		//debug($sErrors);
		if(!empty($this->params['named']['start'])){
			$error = array();
			$step = empty($this->params['named']['step'])?1:$this->params['named']['step'];
			$res = $upgrader->run($error);
			if($res === 'break'){
				$this->redirect(array('start'=>'1','step'=>$step++));
			}
			if($res === true){
				$this->Session->setFlash(__d('upgrader','The database has been fixed', true));
				//$this->redirect(array('plugin'=>'upgrader','controller'=>'upgrader','action'=>'index'));
				$this->redirect('/admin');
			}else{
				$this->Session->setFlash(
					__d('upgrader','An error occurred', true)
					.' :<ul><li>'
					.implode('</li><li>',$error)
					.'</li></ul>'
				);
			}
		}
	}

}
?>