<?php
/*
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
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
*  @copyright  2007-2017 PrestaShop SA
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once _PS_ROOT_DIR_.'/classes/PasswordResetToken.php';

class PasswordControllerCore extends FrontController
{
    public $php_self = 'password';
    public $auth = false;

    /**
     * Start forms process
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        if (Tools::isSubmit('email')) {
            if (!($email = trim(Tools::getValue('email'))) || !Validate::isEmail($email)) {
                $this->errors[] = Tools::displayError('Invalid email address.');
            } else {
                $customer = new Customer();
                $customer->getByemail($email);
                $canSend = Validate::isLoadedObject($customer) && $customer->active;
                $cooldown = (int) Configuration::get('PS_PASSWD_TIME_FRONT');
                if ($canSend && strtotime($customer->last_passwd_gen.'+'.$cooldown.' minutes') <= time()) {
                    $token = PasswordResetTokenCore::create('customer', (int) $customer->id, $customer->passwd);
                    $mail_params = array(
                        '{email}' => $customer->email,
                        '{lastname}' => $customer->lastname,
                        '{firstname}' => $customer->firstname,
                        '{url}' => $this->context->link->getPageLink(
                            'password',
                            true,
                            null,
                            'id_customer='.(int) $customer->id.'&reset_expires='.(int) $token['expires'].'&reset_token='.urlencode($token['token'])
                        ),
                    );
                    if (Mail::Send($this->context->language->id, 'password_query', Mail::l('Password query confirmation'), $mail_params, $customer->email, $customer->firstname.' '.$customer->lastname)) {
                        $customer->last_passwd_gen = date('Y-m-d H:i:s');
                        $customer->update();
                    }
                }

                // Always return the same response so the form cannot be used to enumerate customer accounts.
                $this->context->smarty->assign(array('confirmation' => 2, 'customer_email' => $email));
            }
        } elseif (Tools::getValue('reset_token') && ($id_customer = (int) Tools::getValue('id_customer'))) {
            $customer = new Customer($id_customer);
            if ($this->isCustomerResetRequestValid($customer)) {
                if (Tools::isSubmit('submitReset')) {
                    $password = (string) Tools::getValue('new_password');
                    $confirmation = (string) Tools::getValue('new_password_confirmation');
                    if (!Validate::isPasswd($password, MIN_PASSWD_LENGTH)) {
                        $this->errors[] = sprintf(Tools::displayError('The password must be at least %d characters long.'), MIN_PASSWD_LENGTH);
                    } elseif ($password !== $confirmation) {
                        $this->errors[] = Tools::displayError('The password confirmation does not match.');
                    }

                    if (!count($this->errors)) {
                        $objHash = new PasswordHashing();
                        $customer->passwd = $objHash->passwordHash($password);
                        $customer->last_passwd_gen = date('Y-m-d H:i:s');
                        if ($customer->update()) {
                            Hook::exec('actionPasswordRenew', array('customer' => $customer, 'password' => $password));
                            $mail_params = array(
                                '{email}' => $customer->email,
                                '{lastname}' => $customer->lastname,
                                '{firstname}' => $customer->firstname,
                            );
                            $this->context->smarty->assign(array('confirmation' => 1, 'customer_email' => $customer->email));
                            if (!Mail::Send($this->context->language->id, 'password', Mail::l('Your password was changed'), $mail_params, $customer->email, $customer->firstname.' '.$customer->lastname)) {
                                PrestaShopLogger::addLog('Customer password changed but confirmation email could not be sent', 2, null, 'Customer', (int) $customer->id);
                            }
                        } else {
                            $this->errors[] = Tools::displayError('An error occurred with your account, which prevents us from sending you a new password. Please report this issue using the contact form.');
                        }
                    }
                } else {
                    $this->context->smarty->assign(array(
                        'show_reset_password' => true,
                        'reset_id_customer' => (int) $customer->id,
                        'reset_expires' => (int) Tools::getValue('reset_expires'),
                        'reset_token' => (string) Tools::getValue('reset_token'),
                    ));
                }
            } else {
                $this->errors[] = Tools::displayError('This password reset link is invalid or has expired. Please request a new one.');
            }
        } elseif (Tools::getValue('reset_token') || Tools::getValue('id_customer')) {
            $this->errors[] = Tools::displayError('We cannot regenerate your password with the data you\'ve submitted.');
        }
    }

    protected function isCustomerResetRequestValid($customer)
    {
        if (!Validate::isLoadedObject($customer) || !$customer->active) {
            return false;
        }

        try {
            return PasswordResetTokenCore::isValid(
                'customer',
                (int) $customer->id,
                $customer->passwd,
                (int) Tools::getValue('reset_expires'),
                (string) Tools::getValue('reset_token')
            );
        } catch (RuntimeException $exception) {
            PrestaShopLogger::addLog('Password reset token configuration error', 3);
            return false;
        }
    }

    /**
     * Assign template vars related to page content
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $this->setTemplate(_PS_THEME_DIR_.'password.tpl');
    }

    public function setMedia()
    {
        parent::setMedia();
        if (count($this->errors)) {
            return;
        }
    }
}
