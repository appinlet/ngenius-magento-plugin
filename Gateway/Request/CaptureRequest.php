<?php

namespace NetworkInternational\NGenius\Gateway\Request;

use Magento\Framework\Exception\CouldNotSaveException;
use NetworkInternational\NGenius\Gateway\Config\Config;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\Exception\LocalizedException;
use NetworkInternational\NGenius\Gateway\Request\TokenRequest;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use NetworkInternational\NGenius\Model\CoreFactory;
use Magento\Payment\Helper\Formatter;
use Laminas\Http\Request;
use Ngenius\NgeniusCommon\Formatter\ValueFormatter;
use NetworkInternational\NGenius\Helper\Version;

/**
 * Request builder for payment captures
 *
 * Class CaptureRequest
 */
class CaptureRequest implements BuilderInterface
{
    use Formatter;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var TokenRequest
     */
    protected $tokenRequest;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CoreFactory
     */
    protected $coreFactory;

    /**
     * CaptureRequest constructor.
     *
     * @param Config $config
     * @param TokenRequest $tokenRequest
     * @param StoreManagerInterface $storeManager
     * @param CoreFactory $coreFactory
     */
    public function __construct(
        Config $config,
        TokenRequest $tokenRequest,
        StoreManagerInterface $storeManager,
        CoreFactory $coreFactory
    ) {
        $this->config       = $config;
        $this->tokenRequest = $tokenRequest;
        $this->storeManager = $storeManager;
        $this->coreFactory  = $coreFactory;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     *
     * @return array
     * @throws CouldNotSaveException|LocalizedException
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment   = $paymentDO->getPayment();
        $order     = $paymentDO->getOrder();
        $storeId   = $order->getStoreId();

        $transactionId = $payment->getTransactionId();

        if (!$transactionId) {
            throw new LocalizedException(__('No authorization transaction to proceed capture.'));
        }

        $collection = $this->coreFactory->create()
                                        ->getCollection()
                                        ->addFieldToFilter('order_id', $order->getOrderIncrementId());
        $orderItem  = $collection->getFirstItem();

        $formatPrice       = $this->formatPrice(SubjectReader::readAmount($buildSubject));
        $currencyCode = $orderItem->getCurrency();
        $amount = ValueFormatter::floatToIntRepresentation($currencyCode, $formatPrice);

        if ($this->config->isComplete($storeId)) {
            return [
                'token'   => $this->tokenRequest->getAccessToken($storeId),
                'request' => [
                    'data'   => [
                        'amount'              => [
                            'currencyCode' => $currencyCode,
                            'value'        => $amount
                        ],
                        'merchantDefinedData' => [
                            'pluginName'    => 'magento-2',
                            'pluginVersion' => Version::MODULE_VERSION
                        ]
                    ],
                    'method' => \Laminas\Http\Request::METHOD_POST,
                    'uri'    => $this->config->getOrderCaptureURL(
                        $orderItem->getReference(),
                        $orderItem->getPaymentId(),
                        $storeId
                    )
                ]
            ];
        } else {
            throw new LocalizedException(__('Invalid configuration.'));
        }
    }
}
