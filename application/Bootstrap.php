<?php
/**
 * @author td-office
 *
 *
 */
class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
	protected $view;

    protected function _initPlaceholders()
    {
    	$this->bootstrap('view');
        $view = $this->getResource('view');

        $view->doctype('HTML5');

        $view->headTitle('CSV to DTA Converter')
             ->setSeparator(' :: ');
    }
}
?>