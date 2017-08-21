<?php
/*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */
class ApplicantSubmitModuleFrontController extends ModuleFrontController
{
	private $message = '';

	/**
	 * @see FrontController::postProcess()
	 */
	public function postProcess()
	{
		//$this->message = $this->module->confirmEmail();
	}

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		parent::initContent();

		$this->context->smarty->assign('fields', $this->module->fieldsDefinition);
		if (Tools::isSubmit('submitApplication')) {
			$this->module->submitApplication();

			if ($this->module->error) {
				$this->context->smarty->assign(
					[
					'applicantmsg' => $this->module->error,
					'applicant_value' => isset($_POST['email']) ? pSQL($_POST['email']) : false,
					'applicat_error' => true,
					]
					);
			} elseif ($this->module->valid) {
				$this->context->smarty->assign(
					[
					'applicant_msg' => $this->module->valid,
					'applicant_error' => false
					]
					);
			}

			$tplLocation = 'result.tpl';
		} else {
			$tplLocation = 'form.tpl';
		}

		if (version_compare(_PS_VERSION_, '1.6.9', '>')) {
			$tplLocation = 'module:' . $this->module->name . '/views/templates/front/application_' . $tplLocation;
		}

		$this->setTemplate($tplLocation);
	}
}
