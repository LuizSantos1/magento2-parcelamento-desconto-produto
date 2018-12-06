<?php

namespace Koan\Parcelas\Helper;

class Data {

    const PARCEL_MAX_VALUE = 5;


    protected $_logger;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * Data constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Backend\Block\Template\Context $context
     * @param Data $helper
     * @param array $data
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig)
    {
        $this->_scopeConfig = $scopeConfig;
        $this->_logger = $logger;
    }

    /**
     * Escapa entidades HTML.
     * Função criada para compatibilidade com versões mais antigas do Magento.
     *
     * @param   mixed $data
     * @param   array $allowedTags
     * @return  string
     */
    public function escapeHtml($data, $allowedTags = null) {
        $core_helper = Mage::helper('core');
        if (method_exists($core_helper, "escapeHtml")) {
            return $core_helper->escapeHtml($data, $allowedTags);
        } elseif (method_exists($core_helper, "htmlEscape")) {
            return $core_helper->htmlEscape($data, $allowedTags);
        } else {
            return $data;
        }
    }

    /**
     * Calcula preço da parcela desejada, de acordo com o valor informado e até
     * quantas parcelas sem juros são disponibilizadas. Retorna um array com o
     * valor total, o valor da parcela e uma mensagem extra.
     */
    public function calculateRate($valor_original, $parcelas_sem_juros = 0, $intervalos = 1, $recalcula = false, $juros = 0.0199) {


        $juros = $this->_scopeConfig->getValue('koan_parcelas/general/taxa_juros');
        $desconto = $this->_scopeConfig->getValue('koan_parcelas/general/discount');
        $juros = str_replace(',', '.', $juros);

        $parcelas = $intervalos;
        if ($parcelas_sem_juros > 1 && $parcelas <= $parcelas_sem_juros) {
            $parcelas = $parcelas_sem_juros;
        }

        if ($juros >= 1) {
            $juros /= 100;
        }

        $msg_extra = "";

        $valor_total = $valor_original;

        if ($intervalos == 1 && $parcelas_sem_juros <= 1) {
            if($desconto>0){
                $valor_total = $valor_total; 
                $valor_parcela = $valor_original; 
            }
            $msg_extra = "Sem juros";
        } else {
            if ($parcelas <= $parcelas_sem_juros || $parcelas_sem_juros < 1) {  
                     
                if(($desconto>0) && ($intervalos == 1) && ($parcelas_sem_juros >= 1)){
                    $valor_parcela = $valor_original; 
                    $valor_total = $valor_total; 
                }
                if ($parcelas_sem_juros > 1) {
                    $msg_extra = "Sem juros";
                }
            } else {
                if ($juros == 0) {
                    $valor_parcela = $valor_original / $intervalos;
                    $msg_extra = "Sem juros";
                } else {
                    if ($recalcula) {
                        $valor_parcela = ($valor_original * $juros) / (1 - pow(1 / (1 + $juros), $parcelas_sem_juros));
                        $valor_total = $valor_parcela * $parcelas_sem_juros;
                    }
                    $parcelas -= $parcelas_sem_juros;
                }
            }
            if ($juros != 0 && ( $recalcula || $intervalos > $parcelas_sem_juros)) {
            
                $valor_parcela = ($valor_total * $juros) / (1 - pow(1 / (1 + $juros), $parcelas));
                
                $valor_total = $valor_parcela * $parcelas;
            }
            $valor_parcela = $valor_total / $intervalos;
                
        }


        #$this->_logger->debug($valor_total.' - '.$valor_parcela); 

        return array($valor_total, $valor_parcela, $msg_extra,$intervalos);
    }

    /**
     * Calcula preço à vista com desconto, de acordo com o valor informado e até
     * quantas parcelas sem juros são disponibilizadas. Retorna um array com o
     * valor e a porcentagem de desconto.
     */
    public function calculateUpfrontPrice($valor_original, $parcelas_sem_juros, $juros = 0.0199) {

        if (preg_match("/^[-+]?[0-9]{1,3}(\.[0-9]{3})*(,[0-9]*)?$/", $valor_original)) {
            $valor_original = str_replace(".", "", $valor_original);
            $valor_original = str_replace(",", ".", $valor_original);
        }

        if ($juros > 1) {
            $juros /= 100;
        }

        $valor_a_vista = $valor_original;
        if ($parcelas_sem_juros >= 2 && $juros != 0) {

            $valor_parcela = $valor_a_vista / $parcelas_sem_juros;
            $valor_total = ($valor_parcela * (1 - pow(1 / (1 + $juros), $parcelas_sem_juros))) / $juros;

            $valor_a_vista = $valor_total;
        }

        $desconto = ceil((1 - $valor_a_vista / $valor_original) * 100);

        $valor_a_vista = number_format($valor_a_vista, 2, ",", "");

        return array($valor_a_vista, $desconto);
    }

    /**
     * Calcula planos de parcelamento de acordo com o valor e o número de parcelas
     * sem juros a serem exibidas.
     */
    public function calculateInstallments($valor_total_orig, $parcelas_sem_juros = 0, $recalcula = false, $juros = 0.0199, $parcelas_max = 18, $valor_minimo = 0) {

        $installments = array();

        if (preg_match("/^[-+]?[0-9]{1,3}(\.[0-9]{3})*(,[0-9]*)?$/", $valor_total_orig)) {
            $valor_total_orig = str_replace(".", "", $valor_total_orig);
            $valor_total_orig = str_replace(",", ".", $valor_total_orig);
        }

        if ($parcelas_max < 1) {
            $parcelas_max = 1;
        }

        $n = floor($valor_total_orig / $valor_minimo);
        if ($n > $parcelas_max) {
            $n = $parcelas_max;
        } elseif ($n < 1) {
            $n = 1;
        }

        for ($parcels = 1; $parcels <= $parcelas_max; $parcels++) {

            list($valor_total, $valor_parcela, $msg_extra) = $this->calculateRate($valor_total_orig, $parcelas_sem_juros, $parcels, $recalcula, $juros);

            if ($parcels > 1 &&  $valor_parcela < $valor_minimo) {
                break;
            }

            $valor_parcela = number_format($valor_parcela, 2, ",", "");
            $valor_total = number_format($valor_total, 2, ",", "");

            $installments[] = array(
                'valor_parcela' => $valor_parcela,
                'valor_total' => $valor_total,
                'msg_extra' => $msg_extra,
                'parcel' => $parcels,
                'parcels' => $n,
            );
        }

        return $installments;
    }


    public function ceiling($value, $precision = 0) {
        return ceil($value * pow(10, $precision)) / pow(10, $precision);
    }



}