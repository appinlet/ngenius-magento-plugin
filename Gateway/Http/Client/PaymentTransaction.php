<?php

namespace NetworkInternational\NGenius\Gateway\Http\Client;

use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Model\Method\Logger;
use NetworkInternational\NGenius\Setup\InstallData;
use Ngenius\NgeniusCommon\NgeniusHTTPCommon;
use Ngenius\NgeniusCommon\NgeniusHTTPTransfer;
use NetworkInternational\NGenius\Gateway\Config\Config;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Payment\Gateway\Http\ClientInterface;
use NetworkInternational\NGenius\Model\CoreFactory;

class PaymentTransaction implements ClientInterface
{
    private $logger;
    protected Session $checkoutSession;
    protected array $orderStatus;
    protected ManagerInterface $messageManager;
    protected Config $config;
    protected StoreManagerInterface $storeManager;
    protected CoreFactory $coreFactory;

    /**
     * PaymentTransaction constructor.
     *
     * @param Logger $logger
     * @param Session $checkoutSession
     * @param ManagerInterface $messageManager
     * @param Config $config
     * @param StoreManagerInterface $storeManager
     * @param CoreFactory $coreFactory
     */
    public function __construct(
        Logger $logger,
        Session $checkoutSession,
        ManagerInterface $messageManager,
        Config $config,
        StoreManagerInterface $storeManager,
        CoreFactory $coreFactory,
    ) {
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->orderStatus = InstallData::getStatuses();
        $this->messageManager = $messageManager;
        $this->config = $config;
        $this->storeManager = $storeManager;
        $this->coreFactory = $coreFactory;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @return array|null
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function placeRequest($requestData): ?array
    {
        if (is_array($requestData)) {
            $token = $requestData['token'];
            $url = $requestData['request']['uri'];
            $data = $requestData['request']['data'];
            $method = $requestData['request']['method'];
        } else {
            $token = $requestData->getHeaders()['Token'];
            $url = $requestData->getUri();
            $data = $requestData->getBody();
            $method = $requestData->getMethod();
        }

        $storeId = $this->storeManager->getStore()->getId();
        $ngeniusHttpTransfer = new NgeniusHTTPTransfer($url, $this->config->getHttpVersion($storeId));
        $ngeniusHttpTransfer->setPaymentHeaders($token);
        $ngeniusHttpTransfer->setMethod($method);
        $ngeniusHttpTransfer->setData($data);

        $response = NgeniusHTTPCommon::placeRequest($ngeniusHttpTransfer);

        return $this->postProcess($response);
    }

    /**
     * Processing of API request body
     *
     * @param array $data
     *
     * @return string
     */
    protected function preProcess(array $data): string
    {
        return json_encode($data);
    }

    /**
     * Processing of API response
     *
     * @param string $responseEnc
     * @return null|array
     * @throws Exception
     */
    protected function postProcess(string $responseEnc): ?array
    {
        $response = json_decode($responseEnc);
        if (isset($response->_links->payment->href)) {
            $data = $this->checkoutSession->getData();

            $data['reference'] = $response->reference ?? '';
            $data['action'] = $response->action ?? '';
            $data['state'] = $response->_embedded->payment[0]->state ?? '';
            $data['status'] = $this->orderStatus[0]['status'];
            $data['order_id']  = $data['last_real_order_id'];
            $data['entity_id'] = $data['last_order_id'];
            $data['currency'] = $response->amount->currencyCode;

            $model = $this->coreFactory->create();
            $model->addData($data);
            $model->save();

            $this->checkoutSession->setPaymentURL($response->_links->payment->href);
            return ['payment_url' => $response->_links->payment->href];
        }elseif (isset($response->errors)) {
            return ['message' => 'Message: ' . $response->message . ': ' . $response->errors[0]->message];
        }  else {
            return null;
        }
    }
}