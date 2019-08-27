<?php

declare(strict_types=1);

namespace Lardev\CurrencyConverter\Model\Currency\Import;

use Magento\Directory\Model\Currency\Import\AbstractImport;
use Magento\Directory\Model\CurrencyFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Framework\Xml\Parser;
use Magento\Store\Model\ScopeInterface;

class Ecb extends AbstractImport
{
    /**
     * @var string
     */
    const CURRENCY_CONVERTER_URL = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
    const CURRENCY_CONVERTOR_NAME = 'European Central Bank';
    private $data = array();
    private $scopeConfig;
    protected $httpClientFactory;
    protected $parser;

    public function __construct(
        Parser $parser,
        CurrencyFactory $currencyFactory,
        ScopeConfigInterface $scopeConfig,
        ZendClientFactory $httpClientFactory
    ) {
        $this->parser = $parser;
        $this->httpClientFactory = $httpClientFactory;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($currencyFactory);
    }

    /**
     * Retrieve rate
     *
     * @param string $currencyFrom
     * @param string $currencyTo
     * @return  float
     */
    protected function _convert($currencyFrom, $currencyTo)
    {
        $result = null;
        //$data = $;

        $response = $this->getServiceResponse(self::CURRENCY_CONVERTER_URL, $currencyTo);
        if (count($response)) {
            foreach ($response['gesmes:Envelope']['Cube']['Cube']['_value']['Cube'] as $currency) {
                $data[$currency['_attribute']['currency']] = $currency['_attribute']['rate'];
            }

            return round($data[$currencyTo], 4);
        }


    }

    private function getServiceResponse($url, $currencyTo): array
    {
        /** @var \Magento\Framework\HTTP\ZendClient $httpClient */
        $httpClient = $this->httpClientFactory->create();
        $response = [];

        try {
            $xmlResponse = $httpClient->setUri($url)
                ->setConfig(
                    [
                        'timeout' => $this->scopeConfig->getValue(
                            'currency/ecb/timeout',
                            ScopeInterface::SCOPE_STORE
                        )
                    ]
                )
                ->request('GET')
                ->getBody();

            $response = $this->parser->loadXML($xmlResponse)->xmlToArray();
        } catch (\Exception $e) {
            $this->_messages[] = __('We can\'t retrieve a rate from %1 for %2.', self::CURRENCY_CONVERTOR_NAME, $currencyTo);
        }

        return $response;
    }
}
