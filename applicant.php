<?php
/*
* 2007-2017 PrestaShop
*
*
*/

if (!defined('_PS_VERSION_'))
	exit;

class applicant extends Module
{
	const GUEST_NOT_REGISTERED = -1;
	const CUSTOMER_NOT_REGISTERED = 0;
	const GUEST_REGISTERED = 1;
	const CUSTOMER_REGISTERED = 2;
	const MAIN_TABLE = 'store_applicant';

	static $fields = ['first_name', 'last_name', 'birth_date', 'address', 'phone', 'phone_other', 'email', 'study_level', 'com_exp', 'similar_exp', 'representative', 'fb_acc', 'fb_acc_other', 'fb_admin', 'have_store', 'store_desc', 'application_location', 'application_reasons', 'accurate', 'ip'];

	public $fieldsDefinition = [];

	private $fieldsMetaData;

	private $fieldsTranslation;

	public function __construct()
	{
		$this->name = 'applicant';
		$this->tab = 'front_office_features';
		$this->need_instance = 0;

		$this->controllers = array('submit');

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('Applicant block');
		$this->description = $this->l('Adds a block for job application.');
		$this->confirmUninstall = $this->l('Are you sure that you want to delete all of your candidats?');
		$this->ps_versions_compliancy = array('min' => '1.6', 'max' => '1.8');

		$this->version = '2.3.2';
		$this->author = 'kahla.anouar@yahoo.fr';
		$this->error = false;
		$this->valid = false;
		$this->file = 'export_'.Configuration::get('PS_APPLICANT_RAND').'.csv';
		$this->_files = array(
			'name' => array('applicant_conf', 'applicant_details'),
			'ext' => array(
				0 => 'html',
				1 => 'txt'
			)
		);

		$this->_searched_term = null;

		$this->_html = '';
		$this->loadFieldsTranslation();
		$this->loadEntities();
	}

	public function install()
	{
		if (
			!parent::install()
			|| !Configuration::updateValue('PS_APPLICANT_RAND', rand().rand())
			|| !$this->registerHook(array('header', 'footer'))
			|| !$this->fieldsMetaData
		) {
			return false;
		}

		Configuration::updateValue('NW_SALT', Tools::passwdGen(16));

		$sql = $this->generateSqlInstall();

		return Db::getInstance()->execute($sql);
	}

	private function generateSqlInstall()
	{
		$mainSql = '';
		foreach ($this->fieldsMetaData->entity as $entity) {
			$sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_. $entity['table'] .'` (%s) ENGINE='._MYSQL_ENGINE_.' default CHARSET=utf8;';

			$tableFields = ['`id` INT NOT NULL AUTO_INCREMENT'];

			foreach ($entity->field as $field) {
				$name = (string)$field['name'];
				$length = (int)$field['length'] ? '('.$field['length'].')' : '';
				$nullable = $field['nullable'] ? 'NULL' : 'NOT NULL';
				$default = $field['default'] == '' ? '' : 'DEFAULT '.$field['default'];

				$tableFields[] = sprintf(
					'`%s` %s%s %s %s',
					(string)$name,
					(string)$field['type'],
					(string)$length,
					(string)$nullable,
					(string)$default
				);

			}

			$tableFields[] = 'PRIMARY KEY (`id`)';
			$mainSql .= sprintf($sql, implode(',', $tableFields));
		}

		return $mainSql;
	}

	public function uninstall()
	{
		foreach ($this->fieldsMetaData->entity as $entity) {
			Db::getInstance()->execute('DROP TABLE '._DB_PREFIX_.$entity['table']);
		}

		return parent::uninstall();
	}

	public function getContent()
	{
		if (Tools::isSubmit('submitUpdate'))
		{
			Configuration::updateValue('NW_CONFIRMATION_EMAIL', (bool)Tools::getValue('NW_CONFIRMATION_EMAIL'));
			Configuration::updateValue('ADMIN_APPLICANT_EMAIL', Tools::getValue('ADMIN_APPLICANT_EMAIL'));
			Configuration::updateValue('ADMIN_CONFIRMATION_EMAIL', (bool)Tools::getValue('ADMIN_CONFIRMATION_EMAIL'));

		} elseif (Tools::isSubmit('submitExport')) {
			$this->exportCsv();
		} elseif (Tools::isSubmit('searchTerm')) {
			$this->_searched_term = Tools::getValue('searched_term');
		}

		if (Tools::isSubmit('viewstore_applicant') && Tools::isSubmit('id')) {
			$this->_html .= $this->renderView(Tools::getValue('id'));
		} else {
			$this->_html .= $this->renderForm();
			$this->_html .= $this->renderSearchForm();
			$this->_html .= $this->renderList();
			$this->_html .= $this->renderExportForm();
		}

		

		return $this->_html;
	}

	public function renderView($id)
	{
		$applicant = $this->getApplicant($id);
		$this->smarty->assign(
			[
				'applicant' => $applicant,
				'fields'    => $this->fieldsDefinition,
				'backLink'  => $this->context->link->getAdminLink('AdminModules', false)
					.'&configure='.$this->name
					.'&token='.Tools::getAdminTokenLite('AdminModules'),
			]
		);

        return $this->display(__FILE__, 'views/templates/admin/applicant_details.tpl');
	}

	public function renderList()
	{
		$fields_list = array(

			'id' => array(
				'title' => $this->l('ID'),
				'search' => false,
			),
			'last_name' => array(
				'title' => $this->l('Lastname'),
				'search' => true,
			),
			'first_name' => array(
				'title' => $this->l('Firstname'),
				'search' => true,
			),
			'email' => array(
				'title' => $this->l('Email'),
				'search' => true,
			),
			'phone' => array(
				'title' => $this->l('phone'),
				'type' => 'text',
				'search' => false,
			),
			'address' => array(
				'title' => $this->l('Address'),
				'type' => 'text',
				'search' => false,
			),
			'created_at' => array(
				'title' => $this->l('Application date'),
				'type' => 'datetime',
				'search' => false,
			),
		);

		$helper_list = New HelperList();
		$helper_list->module = $this;
		$helper_list->title = $this->l('Candidats list');
		$helper_list->shopLinkType = '';
		$helper_list->no_link = true;
		$helper_list->show_toolbar = true;
		$helper_list->simple_header = false;
		$helper_list->identifier = 'id';
		$helper_list->table = self::MAIN_TABLE;
		$helper_list->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name;
		$helper_list->token = Tools::getAdminTokenLite('AdminModules');
		$helper_list->actions = array('view');
		$helper_list->_pagination = array(10, 20, 50, 100, 200);


		$helper_list->listTotal = $this->getApplicantsCount();

		/* Paginate the result */
		if (!Tools::getValue('submitFilter'.$helper_list->table)) {
			$this->context->cookie->{$helper_list->table.'_pagination'} = 10;
		}
		$page = ($page = Tools::getValue('submitFilter'.$helper_list->table)) ? $page : 1;
		$pagination = ($pagination = Tools::getValue($helper_list->table.'_pagination')) ? $pagination : 10;

		/* Retrieve list data */
		$subscribers = $this->getApplicants($pagination, $page);

		$this->_helperlist = $helper_list;

		return $helper_list->generateList($subscribers, $fields_list);
	}

	/**
	 * submit aplication
	 */
	public function submitApplication()
	{

		if (empty($_POST['email']) || !Validate::isEmail($_POST['email'])) {
			return $this->error = $this->l('Invalid email address.');
		}

		/* Subscription */
		if ($_POST['action'] == '1')
		{
			error_log(Configuration::get('ADMIN_APPLICANT_EMAIL'));
			if (Configuration::get('ADMIN_APPLICANT_EMAIL')) {
					// create an entry in the database
				$sql = 'INSERT INTO '._DB_PREFIX_.self::MAIN_TABLE.' (%s) VALUES (\'%s\');';
				$fields = implode(',', self::$fields);
				$data = [];

				foreach (self::$fields as $field) {
					$data[$field] = pSQL($_POST[$field]);
				}

				$data['ip'] = pSQL(Tools::getRemoteAddr());

				$sql = sprintf($sql, implode(',', self::$fields), implode("','", $data));

				if (Db::getInstance()->execute($sql)) {

					$this->sendData(Configuration::get('ADMIN_APPLICANT_EMAIL'), $data);
					if (Configuration::get('NW_CONFIRMATION_EMAIL')) {
						$email = pSQL($_POST['email']);
						$this->sendConfirmationEmail($email);
					}

					$this->valid = $this->l('You have successfully applied to the job.');
				} else {
					error_log($sql);
					return $this->error = $this->l('An error occurred during the subscription process.'). ' ('.Db::getInstance()->getMsgError().')';
				}
			}
		}
	}

	private function getBaseQuery()
	{
		$dbquery = new DbQuery();
		$dbquery->from(self::MAIN_TABLE, 'c');

		if ($this->_searched_term) {
			$dbquery->where('CONCAT(first_name, last_name, email, address, com_exp, store_desc) LIKE \'%'.pSQL($this->_searched_term).'%\'');
		} elseif (Tools::isSubmit('submitExport')) {
			if (Tools::getValue('created_at_from')) {
				$dbquery->where('c.`created_at` >= \''.pSQL(Tools::getValue('created_at_from')).'\' ');
			}

			if (Tools::getValue('created_at_to')) {
				$dbquery->where('c.`created_at` <= \''.pSQL(Tools::getValue('created_at_to')).'\' ');
			}

			if (Tools::getValue('have_store')) {
				$dbquery->where('c.`have_store` = \''.pSQL(Tools::getValue('have_store')).'\' ');
			}
		} elseif (Tools::isSubmit('submitFilter'.self::MAIN_TABLE)) {
			foreach (self::$fields as $field) {
				$filterField = self::MAIN_TABLE.'Filter_'.$field;

				if (Tools::isSubmit($filterField) && Tools::getValue($filterField)) {
					$dbquery->where('c.`'.$field.'` LIKE \'%'.pSQL(Tools::getValue($filterField)).'%\' ');
				}
			}
		}

		return $dbquery;
	}

	public function getApplicant($id)
	{
		$dbquery = $this->getBaseQuery();
		$dbquery->where('c.`id` = '.$id);

		$dbquery->limit(1);

		$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery->build());

		return count($result) ? $result[0] : false;
	}

	public function getApplicants($limit = null, $page = 1)
	{
		$dbquery = $this->getBaseQuery();
		$dbquery->select('c.`id` AS `id`,c.`last_name`, c.`first_name`, c.`email`, c.`address`, c.`phone`, c.`created_at`');

		if (Tools::isSubmit(self::MAIN_TABLE.'Orderby')) {
			$dbquery->orderBy(Tools::getValue(self::MAIN_TABLE.'Orderby').' '.Tools::getValue(self::MAIN_TABLE.'Orderway'));
		} else {
			$dbquery->orderBy('created_at desc');
		}

		if ($limit && $page) {
			$offset = ($page - 1) * $limit;
			$dbquery->limit($limit, $offset);
		}

		$list = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery->build());

		return $list;
	}

	public function getApplicantsCount()
	{
		$dbquery = $this->getBaseQuery();
		$dbquery->select('count(*) as total');

		$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery->build());

		return count($result) ? (int)$result[0]['total'] : 0;
	}

	/**
	 * Send a confirmation email to the applicant
	 *
	 * @param string $email
	 *
	 * @return bool
	 */
	protected function sendConfirmationEmail($email)
	{
		return Mail::Send(
			$this->context->language->id,
			'applicant_confirm',
			Mail::l('Application confirmation', $this->context->language->id),
			[],
			pSQL($email),
			null, null, null, null, null,
			dirname(__FILE__).'/mails/',
			false,
			$this->context->shop->id
		);
	}

	/**
	 * Send data to admin
	 *
	 * @param string $email
	 * @param string $token
	 *
	 * @return bool
	 */
	protected function sendData($email, $data)
	{
		$vars = [];

		foreach ($data as $key => $value) {
			$vars['{'.$key.'}'] = nl2br($value);
			$vars['{'.$key.'_label}'] = isset($this->fieldsDefinition[$key]) ? $this->l($this->fieldsDefinition[$key]['label']) : $key;
		}

		return Mail::Send(
			$this->context->language->id,
			'applicant_details',
			Mail::l('New Applicant', $this->context->language->id),
			$vars,
			$email,
			null, null, null, null, null,
			dirname(__FILE__).'/mails/',
			false,
			$this->context->shop->id
		);
	}

	public function hookDisplayRightColumn($params)
	{
		return $this->hookDisplayLeftColumn($params);
	}

	public function hookDisplayLeftColumn($params)
	{
		return $this->display(__FILE__, 'applicant.tpl');
	}

	public function hookFooter($params)
	{
		return $this->hookDisplayLeftColumn($params);
	}

	public function hookdisplayMaintenance($params)
	{
		return $this->hookDisplayLeftColumn($params);
	}

	public function hookDisplayHeader($params)
	{
		$this->context->controller->addCSS($this->_path.'media/applicant.css', 'all');
		$this->context->controller->addJS($this->_path.'media/applicant.js');
		if ($this->context->language->is_rtl) {
			$this->context->controller->addCSS($this->_path.'media/applicant_rtl.css', 'all');
		}
	}

	public function renderForm()
	{
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Settings'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
					array(
						'type' => 'switch',
						'label' => $this->l('Would you like to send a verification email after subscription?'),
						'name' => 'NW_CONFIRMATION_EMAIL',
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Would you like to send information email about subscription?'),
						'name' => 'ADMIN_CONFIRMATION_EMAIL',
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
					),
					array(
						'type' => 'text',
						'label' => $this->l('Admin email'),
						'name' => 'ADMIN_APPLICANT_EMAIL',
						'class' => 'fixed-width-xl',
						'desc' => $this->l('Destination email.')
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitUpdate';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}

	public function renderExportForm()
	{
		// Getting data...
		$countries = Country::getCountries($this->context->language->id);

		// ...formatting array
		$countries_list = array(array('id' => 0, 'name' => $this->l('All countries')));
		foreach ($countries as $country)
			$countries_list[] = array('id' => $country['id_country'], 'name' => $country['name']);

		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Export applicants'),
					'icon' => 'icon-envelope'
				),
				'input' => array(
					array(
						'type' => 'datetime',
						'label' => $this->l('From'),
						'desc' => $this->l('Filter candidats who have applied since this date.'),
						'name' => 'created_at_from',
						'required' => false,
						'default_value' => (int)$this->context->country->id,
					),
					array(
						'type' => 'datetime',
						'label' => $this->l('To'),
						'desc' => $this->l('Filter candidats who have applied until this date.'),
						'hint' => $this->l('Date of subscription'),
						'name' => 'created_at_to',
						'required' => false,
						'default_value' => (int)$this->context->country->id,
					),
					array(
						'type' => 'select',
						'label' => $this->l('Have a store'),
						'desc' => $this->l('Filter candidats who have a store.'),
						'hint' => $this->l(''),
						'name' => 'have_store',
						'required' => false,
						'options' => array(
                            'query' => array(
                            	['id' => null, 'name' => $this->l('Any')],
                                ['id' => 0, 'name' => $this->l('No')],
                                ['id' => 1, 'name' => $this->l('Yes')],
                                ['id' => 'other', 'name' => $this->l('Other')],
                            ),
                            'id' => 'id',
                            'name' => 'name',
                        ),
						'default_value' => null,
					),
					array(
						'type' => 'hidden',
						'name' => 'action',
					)
				),
				'submit' => array(
					'title' => $this->l('Export .CSV file'),
					'class' => 'btn btn-default pull-right',
					'name' => 'submitExport',
				)
			),
		);

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$helper->id = (int)Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'btnSubmit';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}

	public function renderSearchForm()
	{
				$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Search for candidats'),
					'icon' => 'icon-search'
				),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l('Search term'),
						'name' => 'searched_term',
						'class' => 'fixed-width-xxl',
						'desc' => $this->l('firstname, lastname, email or addresse')
					),
				),
				'submit' => array(
					'title' => $this->l('Search'),
					'icon' => 'process-icon-refresh',
				)
			),
		);

		$helper = new HelperForm();
		$helper->table = $this->table;
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'searchTerm';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => array('searched_term' => $this->_searched_term),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}

	public function getConfigFieldsValues()
	{
		return array(
			'ADMIN_APPLICANT_EMAIL' => Tools::getValue('ADMIN_APPLICANT_EMAIL', Configuration::get('ADMIN_APPLICANT_EMAIL')),
			'NW_CONFIRMATION_EMAIL' => Tools::getValue('NW_CONFIRMATION_EMAIL', Configuration::get('NW_CONFIRMATION_EMAIL')),
			'ADMIN_CONFIRMATION_EMAIL' => Tools::getValue('ADMIN_CONFIRMATION_EMAIL', Configuration::get('ADMIN_CONFIRMATION_EMAIL')),
		);
	}

	public function exportCsv()
	{
		if (!isset($this->context))
			$this->context = Context::getContext();

		$dbquery = $this->getBaseQuery();
		$dbquery->select(implode(',', self::$fields));
		$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($dbquery->build());

		if ($result)
		{
			if (!$nb = count($result)) {
				$this->_html .= $this->displayError($this->l('No results found with these filters!'));
			} elseif (
				$fd = @fopen(dirname(__FILE__).'/'.strval(preg_replace('#\.{2,}#',
					'.',
					Tools::getValue('action'))).'_'.$this->file,
					'w'
				)
			) {
				$export = array_merge(array(self::$fields), $result);

				foreach ($export as $row) {
					$this->myFputCsv($fd, $row);
				}

				fclose($fd);

				$this->_html .= $this->displayConfirmation(
					sprintf($this->l('The .CSV file has been successfully exported: %d results found.'), $nb).'<br />
				<a href="'.$this->context->shop->getBaseURI().'modules/applicant/'.Tools::safeOutput(strval(Tools::getValue('action'))).'_'.$this->file.'">
				<b>'.$this->l('Download the file').' '.$this->file.'</b>
				</a>
				<br />
				<ol style="margin-top: 10px;">
					<li style="color: red;">'.
					$this->l('WARNING: When opening this .csv file with Excel, choose UTF-8 encoding to avoid strange characters.').
					'</li>
				</ol>');
			} else {
				$this->_html .= $this->displayError($this->l('Error: Write access limited').' '.dirname(__FILE__).'/'.strval(Tools::getValue('action')).'_'.$this->file.' !');
			}
		}
		else
			$this->_html .= $this->displayError($this->l('No result found!'));
	}

	private function myFputCsv($fd, $row)
	{
		$line = implode(';', $row);
		$line = str_replace(array("\r\n", "\n\r", "\n", "\r"), ',', $line);
		$line .= "\n";
		if (!fwrite($fd, $line, 4096))
			$this->post_errors[] = $this->l('Error: Write access limited').' '.dirname(__FILE__).'/'.$this->file.' !';
	}

	private function loadFieldsTranslation()
	{
		$this->fieldsTranslation = [
			'Firstname' => $this->l('Firstname'),
			'Lastname' => $this->l('Lastname'),
			'Birth date' => $this->l('Birth date'),
			'Address' => $this->l('Address'),
			'Phone' => $this->l('Phone'),
			'Other phone' => $this->l('Other phone'),
			'Email' => $this->l('Email'),
			'Study level' => $this->l('Study level'),
			'commercial experience' => $this->l('commercial experience'),
			'Similar experience' => $this->l('Similar experience'),
			'Representative' => $this->l('Representative'),
			'Facebook account' => $this->l('Facebook account'),
			'Other facebook account' => $this->l('Other facebook account'),
			'Administred Facebook account' => $this->l('Administred Facebook account'),
			'Owen store' => $this->l('Owen store'),
			'Yes' => $this->l('Yes'),
			'No' => $this->l('No'),
			'Other' => $this->l('Other'),
			'Store description' => $this->l('Store description'),
			'Desired location' => $this->l('Desired location'),
			'application reasons' => $this->l('application reasons'),
			'Accurate informations' => $this->l('Accurate informations'),
			'ip' => $this->l('ip'),
		];
	}

	private function translateLabel($label)
	{
		return isset($this->fieldsTranslation[$label]) ? $this->fieldsTranslation[$label] : $label;

	}

	private function loadEntities()
	{
		$this->fieldsMetaData = @simplexml_load_string($this->display(__FILE__, 'config/entities.xml'));

		if ($this->fieldsMetaData) {

			foreach ($this->fieldsMetaData->entity[0]->field as $field) {
				$name = (string)$field['name'];
				$label = (string)$field['label'];

				$fd = [
				'required' => !$field['nullable'],
				'label'    => $this->translateLabel($label),
				'type'     => (string)$field['html_type'],
				];

				if (isset($field->choices)) {
					foreach ($field->choices->choice as $choice) {
						$id = (string)$choice['id'];
						$choiceLabel = (string)$choice['label'];
						$fd['choices'][$id] = $this->translateLabel($choiceLabel);
					}
				}

				$this->fieldsDefinition[$name] = $fd;
			}
		}
	}
}
