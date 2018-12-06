<?php
/**
 *
 * NOTICE OF LICENSE
 *
 *Licensed under the Apache License, Version 2.0 (the "License");
 *you may not use this file except in compliance with the License.
 *You may obtain a copy of the License at
 *
 *http://www.apache.org/licenses/LICENSE-2.0
 *
 *Unless required by applicable law or agreed to in writing, software
 *distributed under the License is distributed on an "AS IS" BASIS,
 *WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *See the License for the specific language governing permissions and
 *limitations under the License.
 *
 *  @author    KOAN Soluções
 *  @copyright 2018 KOAN Soluções
 *  @license   http://www.apache.org/licenses/LICENSE-2.0
 */

namespace Koan\Parcelas\Block\Product\View;

use Magento\Framework\View\Element\Template;


/**
 * Get all the data to display an installment list in the product view
 *
 */
class Installments extends Template
{
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    protected $_scopeConfig;

    protected $_logger;

    /**
     * @var \Koan\Parcelas\Helper\Data
     */
    protected $_helper;

    /**
     * InstallmentMethod auto-generated factory
     * @var \Koan\Parcelas\Model\Direct\InstallmentsMethod
     */
    protected $_installmentFactory;

    /**
     * @param \Magento\Catalog\Block\Product\Context $context
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfigInterface,
        \Koan\Parcelas\Helper\Data $helper,
        \Magento\Catalog\Block\Product\Context $context
    ) {
        $this->_coreRegistry = $context->getRegistry();
        $this->_logger = $logger;
        $this->_helper = $helper;
        parent::__construct($context);
        /** @var \Magento\Framework\App\Config\ScopeConfigInterface _scopeConfig */
        $this->_scopeConfig = $scopeConfigInterface;
    }
   
    /**
     * Retrieve currently viewed product object
     *
     * @return \Magento\Catalog\Model\Product
     */
    public function getProduct()
    {
        if (!$this->hasData('product')) {
            $this->setData('product', $this->_coreRegistry->registry('product'));
        }
        return $this->getData('product');
    }


    public function getDiscount(){
       return $this->_scopeConfig->getValue('koan_parcelas/general/discount');
    }
    public function getInstallments(){
       return $this->_scopeConfig->getValue('koan_parcelas/general/installments');
    }


    public function getInstallment(){


        /** @var \Koan\Parcelas\Helper\Auth $dataHelper */
        $dataHelper = $this->_helper;

        $max_parcelas = $this->_scopeConfig->getValue('koan_parcelas/general/max_parcelas');
        $valor_minimo = $this->_scopeConfig->getValue('koan_parcelas/general/valor_minimo');
        $parcelas_sem_juros = $this->_scopeConfig->getValue('koan_parcelas/general/parcelas_sem_juros');
        $taxa_juros = $this->_scopeConfig->getValue('koan_parcelas/general/taxa_juros');


        $taxa_juros *= 1 + ($taxa_juros / 100);

        $installmentsAdd = $parcelas_sem_juros;


        $installments = $upfrontPrice = $upfrontDiscount = '';
        $finalValue = $this->getProduct()->getFinalPrice();

       #$this->_logger->debug($finalValue);

        $installmentsShow = (boolean) ($finalValue);

        if ($installmentsShow) {
            $installments = $dataHelper->calculateInstallments($finalValue, $installmentsAdd, false, $taxa_juros, $max_parcelas, $valor_minimo);
        }

        $getMinParcela = $dataHelper->calculateInstallments($finalValue, $installmentsAdd, false, $taxa_juros, $max_parcelas, $valor_minimo);

           
        if (is_array($getMinParcela)) {   
            $arrParcel = end($getMinParcela);
            $minParcel = $arrParcel['valor_parcela'];
            $numParcels = $arrParcel['parcel'];
        }

        return $installments;
    }

    
    /**
     * Validate if the KOAN Parcelas list in the product view is enabled
     * @return bool
     */
    public function isEnabled() {
        $status = $this->_scopeConfig->getValue('koan_parcelas/general/enable');
        return (! is_null($status) && $status == 1) ? true : false;
    }
}
