<?php

namespace PaynlPayment\Components;

use Paynl\Config as SDKConfig;
use Shopware\Components\Plugin\ConfigReader;
use Shopware\Components\Plugin\DBALConfigReader;
use Shopware\Models\Shop\Shop;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

class Config
{
    /**
     * @var ConfigReader
     */
    protected $configReader;

    /**
     * @var DBALConfigReader
     */
    protected $dbConfigReader;

    /**
     * @var array
     */
    protected $data = null;

    /**
     * @var Shop
     */
    protected $shop;

    public function __construct(ConfigReader $configReader, DBALConfigReader $dbConfigReader)
    {
        $this->configReader = $configReader;
        $this->dbConfigReader = $dbConfigReader;
    }

    public function setShop(Shop $shop)
    {
        $this->shop = $shop;
        $this->data = null;
    }

    /**
     * Get a config value
     *
     * @param string $key Key of the config value, leave empty to get all configs for this plugin
     * @param mixed $default If the config key is not found, this value will be returned
     */
    public function get($key = null, $default = null)
    {
        if (is_null($this->data)) {
            $shop = $this->getShop();

            $parts = explode('\\', __NAMESPACE__);
            $pluginName = array_shift($parts);

            $this->data = $this->configReader->getByPluginName($pluginName, $shop);

            $useCache = $this->data['use_cache'] ?? false;
            if (!$useCache) {
                $this->data = $this->dbConfigReader->getByPluginName($pluginName, $shop);
            }
        }

        if (!is_null($key)) {
            return isset($this->data[$key]) ? $this->data[$key] : $default;
        }

        return $this->data;
    }

    /**
     * @return string
     */
    public function tokenCode()
    {
        return $this->get('tokenCode');
    }

    /**
     * @return string
     */
    public function apiToken()
    {
        return $this->get('apiToken');
    }

    /**
     * @return string
     */
    public function serviceId()
    {
        return $this->get('serviceId');
    }

    /**
     * @return bool
     */
    public function testMode()
    {
        return $this->get('testMode', false);
    }

    /**
     * @return bool
     */
    public function sendStatusMail()
    {
        return $this->get('status_mail', false);
    }

    /**
     * @return bool
     */
    public function banksIsAllowed()
    {
        return $this->get('show_banks', false);
    }

    /**
     * @return string
     */
    public function showDescription()
    {
        return $this->get('show_description', 'show_payment_information');
    }

    /**
     * @return bool
     */
    public function isRefundAllowed()
    {
        return $this->get('allow_refunds', false);
    }

    public function useAdditionalAddressFields()
    {
        return $this->get('additional_address_fields', false);
    }

    /**
     * @return array Female salutations to determine the gender of the customer
     */
    public function femaleSalutations()
    {
        $salutations = $this->get('female_salutations', "mrs, ms, miss, ma'am, frau, mevrouw, mevr");
        $arrSalutations = explode(',', $salutations);

        return array_map('trim', $arrSalutations);
    }

    public function loginSDK()
    {
        SDKConfig::setTokenCode($this->tokenCode());
        SDKConfig::setApiToken($this->apiToken());
        SDKConfig::setServiceId($this->serviceId());
    }

    protected function getShop()
    {
        if ($this->shop) {
            return $this->shop;
        }

        try {
            $newShop = Shopware()->Shop();
        } catch (ServiceNotFoundException $e) {
            $newShop = null;
        }

        return $newShop;
    }
}
