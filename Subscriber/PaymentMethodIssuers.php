<?php

namespace PaynlPayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_Action;
use Enlight_View;
use PaynlPayment\Components\IssuersProvider;

class PaymentMethodIssuers implements SubscriberInterface
{
    private $issuersProvider;

    public function __construct(IssuersProvider $issuersProvider)
    {
        $this->issuersProvider = $issuersProvider;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onPostDispatchCheckout',
        ];
    }

    public function onPostDispatchCheckout(\Enlight_Event_EventArgs $args)
    {
        /** @var Enlight_Controller_Action $controller */
        $controller = $args->getSubject();

        $request = $args->getRequest();
        $controllerName = $request->getControllerName();
        if ($controllerName != 'checkout') {
            return;
        }

        $action = $request->getActionName();

        /** @var Enlight_View $view */
        $view = $controller->View();

        /** @var \Enlight_Components_Session_Namespace $session */
        $session = Shopware()->Session();
        if ($action == 'saveShippingPayment') {
            $session->paynlIssuer = Shopware()->Front()->Request()->getPost('paynlIssuer');
        }

        if ($action == 'confirm' && !empty($session->paynlIssuer)) {
            $bankData = [];
            foreach ($this->issuersProvider->getIssuers() as $bank) {
                if ($bank->id == $session->paynlIssuer) {
                    $bankData = $bank;
                    break;
                }
            }
            $view->assign('bankData', $bankData);
        }

        if ($action == 'shippingPayment') {
            $issuers = $this->issuersProvider->getIssuers();
            $view->assign('paynlSelectedIssuer', $session->paynlIssuer);
            $view->assign('paynlIssuers', $issuers);
            $session->paynlIssuer = null;
        }
    }
}
