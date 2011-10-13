<?php

class Application_Form_ConvertCsv extends Zend_Form
{
	public function init()
	{
		$file = new Zend_Form_SubForm();

		$file->addElement('file', 'csvFile', array(
			'label'		=> 'file',
			'required'	=> true
		));

		$file->addElement('text', 'csvSeparator', array(
            'label'      => 'separator',
            'required'   => true
        ));

        $fieldInfos = new Zend_Form_SubForm();

        $fieldInfos->addElement('text', 'name', array(
			'label'		=> 'name of reciever field',
			'required'	=> true
		));

        $fieldInfos->addElement('text', 'bank_code', array(
			'label'		=> 'name of bank code field',
			'required'	=> true
		));

		$fieldInfos->addElement('text', 'account_number', array(
			'label'		=> 'name of account number field',
			'required'	=> true
		));

		$fieldInfos->addElement('text', 'amount', array(
			'label'		=> 'name of amount field',
			'required'	=> true
		));

		$fieldInfos->addElement('text', 'purposes', array(
			'label'		=> 'name of purposes field',
			'required'	=> true
		));

		$actions = new Zend_Form_SubForm();

		$actions->addElement('submit', 'submit', array(
            'ignore'   => true,
            'label'    => 'generate dta file',
        ));

		$this->addSubForms(array(
			'csvFile' 				=> $file,
			'recieverFieldNames'	=> $fieldInfos,
			'actions'				=> $actions
		));
	}
}
?>