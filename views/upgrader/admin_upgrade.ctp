<h1><?php __('Your database need to be upgraded'); ?></h1>
<p><?php __('The system detected that your database has an old format. You can upgrade your database automatically by clicking the following button.'); ?></p>
<a href="<?php echo $this->Html->url(array($name,'start'=>'1')); ?>"><?php __('Start'); ?></a>