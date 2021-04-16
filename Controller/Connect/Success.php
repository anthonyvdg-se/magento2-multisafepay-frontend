<?php
/**
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is provided with Magento in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * Copyright © 2021 MultiSafepay, Inc. All rights reserved.
 * See DISCLAIMER.md for disclaimer details.
 *
 */

declare(strict_types=1);

namespace MultiSafepay\ConnectFrontend\Controller\Connect;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use MultiSafepay\ConnectCore\Logger\Logger;
use MultiSafepay\ConnectCore\Util\CustomReturnUrlUtil;
use MultiSafepay\ConnectCore\Util\OrderStatusUtil;
use MultiSafepay\ConnectFrontend\Validator\RequestValidator;

class Success extends Action
{
    /**
     * @var OrderStatusUtil
     */
    protected $orderStatusUtil;

    /**
     * @var OrderInterfaceFactory
     */
    private $orderFactory;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var RequestValidator
     */
    private $requestValidator;

    /**
     * @var CustomReturnUrlUtil
     */
    private $customReturnUrlUtil;

    /**
     * Success constructor.
     *
     * @param Context $context
     * @param OrderInterfaceFactory $orderFactory
     * @param OrderStatusUtil $orderStatusUtil
     * @param RequestValidator $requestValidator
     * @param Session $checkoutSession
     * @param Logger $logger
     * @param CartRepositoryInterface $cartRepository
     * @param CustomReturnUrlUtil $customReturnUrlUtil
     */
    public function __construct(
        Context $context,
        OrderInterfaceFactory $orderFactory,
        OrderStatusUtil $orderStatusUtil,
        RequestValidator $requestValidator,
        Session $checkoutSession,
        Logger $logger,
        CartRepositoryInterface $cartRepository,
        CustomReturnUrlUtil $customReturnUrlUtil
    ) {
        parent::__construct($context);
        $this->orderFactory = $orderFactory;
        $this->requestValidator = $requestValidator;
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->cartRepository = $cartRepository;
        $this->customReturnUrlUtil = $customReturnUrlUtil;
        $this->orderStatusUtil = $orderStatusUtil;
    }

    /**
     * @inheritDoc
     * @throws LocalizedException
     */
    public function execute()
    {
        $parameters = $this->getRequest()->getParams();

        if (!$this->requestValidator->validate($parameters)) {
            $customReturnUrl = $this->customReturnUrlUtil->getCustomReturnUrlByType(
                $this->checkoutSession->getLastRealOrder(),
                $parameters,
                CustomReturnUrlUtil::SUCCESS_URL_TYPE_NAME
            );

            if ($customReturnUrl) {
                return $this->resultRedirectFactory->create()->setUrl($customReturnUrl);
            }

            return $this->_redirect('checkout/cart');
        }

        $orderId = $parameters['transactionid'];

        /** @var OrderInterface $order */
        $order = $this->orderFactory->create()->loadByIncrementId($orderId);
        $customReturnUrl = $this->customReturnUrlUtil->getCustomReturnUrlByType(
            $order,
            $parameters,
            CustomReturnUrlUtil::SUCCESS_URL_TYPE_NAME
        );

        if ($customReturnUrl) {
            $redirectUrl = $this->resultRedirectFactory->create()->setUrl($customReturnUrl);
        } else {
            $redirectUrl = $this->_redirect('checkout/onepage/success?utm_nooverride=1');
        }
      
        $order->addCommentToStatusHistory('User redirected to the success page.');

        $this->setCheckoutSessionData($order);
        $this->logger->logPaymentSuccessInfo($orderId);

        return $redirectUrl;
    }

    /**
     * @param OrderInterface $order
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function setCheckoutSessionData(OrderInterface $order)
    {
        $this->checkoutSession->unsQuoteId();

        $quote = $this->checkoutSession->getQuote();
        $quote->setIsActive(false);

        $this->cartRepository->save($quote);

        $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastOrderId($order->getEntityId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());
    }
}
