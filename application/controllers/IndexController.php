<?php
/**
 * @author td-office
 *
 *
 */
class IndexController extends Zend_Controller_Action
{
	protected $_bankAccount = array();
	protected $_flashMessanger;

	public function init()
	{
		$this->_bankAccount['name'] = 'topdeals GmbH';
		$this->_bankAccount['bank_code'] = '20070024';
		$this->_bankAccount['account_number'] = '123456';

		$this->_flashMessanger	= $this->_helper->getHelper('flashMessenger');
	}

	public function indexAction()
	{
		$request					= $this->getRequest();
		$form						= new Application_Form_ConvertCsv();
		$this->view->convertCsvForm	= $form;

		if ($this->getRequest()->isPost()) {
			if ($form->isValid($request->getPost())) {
				$this->_convertFile();
			}
			else {
				$this->view->formError = $form->getErrorMessages();
			}
		}
	}

	protected function _convertFile()
	{
		$request	= $this->getRequest();

		if ($_FILES['csvFile']['error'] !== 0) {
			throw new Zend_Controller_Exception('Error while uploading csv file.');
		}
		$csvFile	= $request->getParam('csvFile');

		if ($csvFile['csvSeparator'] == '') {
			throw new Zend_Controller_Exception('No separator given.');
		}

		try {
			$recieverData	= $this->parseCsvToArray($_FILES['csvFile']['tmp_name']);
			$dataKeys		= $this->_getFieldKeysByNames($recieverData['titles'], $request->getParam('recieverFieldNames'));
			$dtaData		= $this->_prepareRecieverData($recieverData['data'], $dataKeys);

			include 'Payment/DTA.php';
			$dta = new DTA(DTA_CREDIT);
			$result = $dta->setAccountFileSender(array(
				'name'				=> $dta->makeValidString($this->_bankAccount['name']),
				'bank_code'			=> $this->_bankAccount['bank_code'],
				'account_number'	=> $this->_bankAccount['account_number']
			));

			try {
				$numSavedTransactions	= 0;
				$sumBankCodes			= 0;
				$sumAccountNumbers		= 0;
				$sumAmounts				= 0;
				foreach ($dtaData as $dtaRecord) {
					$result	= $dta->addExchange(
						array(
							'name'				=> $dta->makeValidString($dtaRecord['name']),
							'bank_code'			=> $dtaRecord['bank_code'],
							'account_number'	=> $dtaRecord['account_number']
						), $this->_formatAmount($dtaRecord['amount']), $dtaRecord['purposes']
					);

					if (false === $result) {
						throw new Payment_DTA_Exception('Fehlerhafter Datensatz: ' . implode(';', $dtaRecord) . '; ' . implode(', ', $dta->getParsingErrors()));
					}
					else {
						$numSavedTransactions++;
						$sumBankCodes		+= $dtaRecord['bank_code'];
						$sumAccountNumbers	+= $dtaRecord['account_number'];
						$sumAmounts			+= $this->_formatAmount($dtaRecord['amount']);
					}
				}

				$filePath	= '/dtaFiles/DTAUS0.txt';
				$result		= $dta->saveFile($_SERVER['DOCUMENT_ROOT'] . $filePath);

				if (false === $result) {
					throw new Payment_DTA_Exception('Could not write dta file!');
				}

				$this->_sendFile($filePath);

				$this->_flashMessanger->addMessage(array('success' => $numSavedTransactions . ' have been created.'));
				$this->view->messages = $this->_flashMessanger->getMessages();
			} catch (Payment_DTA_Exception $e) {
				$this->_flashMessanger->addMessage(array('error' => $e->getMessage()));
				$this->view->messages = $this->_flashMessanger->getMessages();
			}

		} catch (Zend_Controller_Exception $e) {
			echo $e->getMessage();
			echo 'The file could not be processed.';
		}
	}

	/**
	 * Reads a file and sends it tho the client.
	 *
	 * @param string $filePath
	 * @return void
	 */
	protected function _sendFile($filePath)
	{
		$mtime	= $mtime = filemtime($filePath);
		$size	= intval(sprintf("%u", filesize($filePath)));

		header("Content-type: application/force-download");
		header('Content-Type: application/octet-stream');

		if (strstr($_SERVER["HTTP_USER_AGENT"], "MSIE") != false) {
			header("Content-Disposition: attachment; filename=" . urlencode(basename($filePath)) . '; modification-date="' . date('r', $mtime) . '";');
		} else {
			header("Content-Disposition: attachment; filename=\"" . basename($filePath) . '"; modification-date="' . date('r', $mtime) . '";');
		}

		header("Content-Length: " . $size);

		set_time_limit(300);

		$chunksize = 1 * (1024 * 1024); // how many bytes per chunk
		if ($size > $chunksize) {
			$handle = fopen($filePath, 'rb');
			$buffer = '';

			while (!feof($handle)) {
				$buffer = fread($handle, $chunksize);
				echo $buffer;
				ob_flush();
				flush();
			}

			fclose($handle);
		} else {
			readfile($filePath);
		}
	}

	/**
	 * Formats the amount to float.
	 *
	 * @param mixed $amount The amount given in the csv file.
	 * @return float
	 */
	protected function _formatAmount($amount)
	{
		$amount	= str_replace('.', '', $amount);
		$amount	= str_replace(',', '.', $amount);
		$amount	= floatval(number_format($amount, 2, '.', ''));

		return	$amount;
	}


	protected function _prepareRecieverData($csvData, $dataKeys)
	{
		$dtaData			= array();
		$dtaRecordMinData	= array('name' => '', 'bank_code' => '', 'account_number' => '');
		$i					= 0;
		foreach ($csvData as $record) {
			$dtaData[$i]	= $dtaRecordMinData;

			foreach ($dataKeys as $dtaKey => $value) {
				$foundValues	= array();
				$values			= explode(';', $value);

				foreach ($values as $csvKey) {
					$foundValues[]	= trim(str_replace(
						array('ä', 'Ä', 'ö', 'Ö', 'ü', 'Ü', 'ß', '€'),
						array('0x7B', '0x5B', '0x7C', '0x5C', '0x5D', '0x7D', '0x7E', ''),
						$record[$csvKey]
					));
				}

				$dtaData[$i][$dtaKey]	= implode(', ', $foundValues);
			}
			$i++;
		}

		return $dtaData;
	}

	/**
	 * Returns the keys pointing to the corresponding data fields.
	 *
	 * @param array $csvTitles An array containing the titles given in the csv file.
	 * @param array $fieldNames The field names defined by the user.
	 * @return array
	 */
	protected function _getFieldKeysByNames($csvTitles, $fieldNames)
	{
		$nameKeys = array();
		foreach ($fieldNames as $fieldName => $csvTitleNames) {
			$csvTitleNames = explode(';', $csvTitleNames);

			foreach ($csvTitleNames as $csvTitleName) {
				$key	= array_search($csvTitleName, $csvTitles);

				if (false !== $key) {
					$nameKeys[$fieldName] = (isset($nameKeys[$fieldName]))
						? $nameKeys[$fieldName] . ';' . array_search($csvTitleName, $csvTitles)
						: array_search($csvTitleName, $csvTitles);
				}
				else {
					throw new Payment_DTA_Exception('The field "' . $csvTitleName . '" does not exist in the csv file.');
				}
			}
		}

		return $nameKeys;
	}

	/**
	 * Returns the csv data splitted into two dimensions. The first (key=titles) contains the csv titles, the second (key=data) contains the data.
	 *
	 * @param string $filename The path to the csv file.
	 * @throws Zend_Controller_Exception
	 * @return array
	 */
	protected function parseCsvToArray($filename)
	{
		$return = array();
		$row = 1;
		$fh = fopen($filename, 'r');

		// Read first line to "remove" column titles
		$return['titles'] = $data = fgetcsv ($fh, 4096, ';');

		if ($return['titles'] === false) {
			throw new Payment_DTA_Exception('The given file is not formated as csv.');
		}

		while (($data = fgetcsv ($fh, 4096, ';')) !== FALSE) {
			$return['data'][] = $data;
		}
		fclose ($fh);

		return $return;
	}
}
