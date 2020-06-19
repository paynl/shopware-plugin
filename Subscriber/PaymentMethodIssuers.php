<?php

namespace PaynlPayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Action;
use Enlight_View;
use PaynlPayment\Components\Config;
use PaynlPayment\Components\IssuersProvider;
use PaynlPayment\Helpers\CustomerHelper;
use Zend_Session_Abstract;
use PaynlPayment\Helpers\ExtraFieldsHelper;

class PaymentMethodIssuers implements SubscriberInterface
{
    /**
     * @var Zend_Session_Abstract
     */
    private $session;
    /**
     * @var Config
     */
    private $config;

    private $issuersProvider;
    /**
     * @var CustomerHelper
     */
    private $customerHelper;
    /**
     * @var ExtraFieldsHelper
     */
    private $extraFieldsHelper;

    public function __construct(
        Zend_Session_Abstract $session,
        Config $config,
        IssuersProvider $issuersProvider,
        CustomerHelper $customerHelper,
        ExtraFieldsHelper $extraFieldsHelper
    )
    {
        $this->session = $session;
        $this->config = $config;
        $this->issuersProvider = $issuersProvider;
        $this->customerHelper = $customerHelper;
        $this->extraFieldsHelper = $extraFieldsHelper;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onPostDispatchCheckout',
            'Enlight_Controller_Action_PostDispatch_Frontend_Account' => 'onPostDispatchAccount',

            'Shopware_Modules_Admin_UpdatePayment_FilterSql' => 'onUpdatePaymentForUser',
        ];
    }

    public function onUpdatePaymentForUser(\Enlight_Event_EventArgs $args)
    {
        $defaultReturn = $args->getReturn();
        /** @var \Enlight_Controller_Request_Request $request */
        $request = Shopware()->Front()->Request();

        $this->storeExtraFields($request);
        $this->storeDobAndPhone($request);

        return $defaultReturn;
    }

    public function onPostDispatchAccount(\Enlight_Event_EventArgs $args)
    {
        $userId = $this->session->sUserId;
        if (empty($userId)) {
            return;
        }

        $request = $args->getRequest();
        $controller = $args->getSubject();
        /** @var Enlight_View $view */
        $view = $controller->View();
        $action = $request->getActionName();
        $issuerId = $this->extraFieldsHelper->getSelectedIssuer();

        $customerDobAndPhone = $this->customerHelper->getDobAndPhoneByCustomerId($userId);
        if (!isset($customerDobAndPhone['dob']) || empty($customerDobAndPhone['dob'])) {
            $view->assign('showDobField', true);
        }
        if (!isset($customerDobAndPhone['phone']) || empty($customerDobAndPhone['phone'])) {
            $view->assign('showPhoneField', true);
        }

        if ($action == 'payment') {
            $view->assign('paynlIssuers', $this->issuersProvider->getIssuers());
            $view->assign('paynlSelectedIssuer', $issuerId);
        }

        if ($action == 'index') {
            $bankData = [];
            foreach ($this->issuersProvider->getIssuers() as $bank) {
                if ($bank->id == $issuerId) {
                    $bankData = $bank;
                    break;
                }
            }

            $view->assign('bankData', $bankData);
        }
    }

    public function onPostDispatchCheckout(\Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Action $controller */
        $controller = $args->getSubject();

        $request = $args->getRequest();
        $controllerName = $request->getControllerName();
        $userId = $this->session->sUserId;
        if ($controllerName != 'checkout' || empty($userId)) {
            return;
        }

        $action = $request->getActionName();

        /** @var Enlight_View $view */
        $view = $controller->View();
        $issuerId = $this->extraFieldsHelper->getSelectedIssuer();

        if ($action == 'confirm' && !empty($issuerId)) {
            $bankData = [];

            $selectedPaymentMethodName =
                $this->session->sOrderVariables['sUserData']['additional']['payment']['description'];
            if ($selectedPaymentMethodName == 'iDEAL') {
                foreach ($this->issuersProvider->getIssuers() as $bank) {
                    if ($bank->id == $issuerId) {
                        $bankData = $bank;
                        break;
                    }
                }

                $view->assign('bankData', $bankData);
            } else {
                $this->extraFieldsHelper->clearSelectedIssuer($this->session->sUserId);
                $view->assign('bankData', $bankData);
            }
        }

        $isCancelled = false;
        if ($action === 'confirm') {
            $isCancelled = (bool)Shopware()->Front()->Request()->get('isCancelled', 0);
        }

        $view->assign('isCancelled', $isCancelled);

        if ($action == 'shippingPayment') {
            $customerDobAndPhone = $this->customerHelper->getDobAndPhoneByCustomerId($userId);
            if (!isset($customerDobAndPhone['dob']) || empty($customerDobAndPhone['dob'])) {
                $view->assign('showDobField', true);
            }
            if (!isset($customerDobAndPhone['phone']) || empty($customerDobAndPhone['phone'])) {
                $view->assign('showPhoneField', true);
            }

            if ($action == 'shippingPayment' && $this->config->banksIsAllowed()) {
                $view->assign('paynlSelectedIssuer', $issuerId);
                $issuers = $this->issuersProvider->getIssuers();
                $view->assign('paynlIssuers', $issuers);
            }
        }
    }

    /**
     * @param \Enlight_Controller_Request_Request $request
     * @throws \Exception
     */
    private function storeExtraFields(\Enlight_Controller_Request_Request $request): void
    {
        $extraFieldsData = [
            'idealIssuer' => (int)$request->getPost('paynlIssuer')
        ];

        $this->extraFieldsHelper->saveExtraFields($extraFieldsData, $this->session->sUserId);
    }

    /**
     * @param \Enlight_Controller_Request_Request $request
     */
    private function storeDobAndPhone(\Enlight_Controller_Request_Request $request): void
    {
        $payment = $request->getPost('payment');
        if (!empty($request->getPost('register')) && isset($request->getPost('register')['payment'])) {
            $payment = $request->getPost('register')['payment'];
        }
        $phone = $request->getPost('phone');
        $dob = $request->getPost('dob');

        $userId = $this->session->sUserId;

        if (isset($phone[$payment]) && !empty($phone[$payment])) {
            $this->customerHelper->storePhone($userId, $phone[$payment]);
        }

        if (isset($dob[$payment]) && !empty($dob[$payment])) {
            $this->customerHelper->storeUserBirthday($userId, $dob[$payment]);
        }
    }
}
