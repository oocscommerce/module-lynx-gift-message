<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxGiftMessage\Plugin;

use Magento\Catalog\Model\Product\Attribute\Source\Boolean;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\GiftMessage\Api\CartRepositoryInterface;
use Magento\GiftMessage\Api\Data\MessageInterface;
use Magento\GiftMessage\Api\ItemRepositoryInterface;
use Magento\GiftMessage\Helper\Message as GiftMessageHelper;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use OOCS\LynxThemeComponent\Provider\Checkout\Cart;

/**
 * The cart's gift messages as core's gift options offer them there (GiftMessageConfigProvider)
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class CartGiftMessage
{
    /**
     * Checkout session, scope config, the order-level and item-level message repositories
     *
     * @param CheckoutSession $checkoutSession
     * @param ScopeConfigInterface $scopeConfig
     * @param CartRepositoryInterface $cartRepository
     * @param ItemRepositoryInterface $itemRepository
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ItemRepositoryInterface $itemRepository,
    ) {
    }

    /**
     * Add the order's message and each allowed item's, while either level is on
     *
     * @param Cart $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterResolve(Cart $subject, array $result): array
    {
        $quote = $this->checkoutSession->getQuote();
        if (!$quote->getId() || !$quote->getItemsCount()) {
            return $result;
        }

        $quoteId = (int) $quote->getId();
        $orderAllowed = !$quote->isVirtual() && $this->scopeConfig->isSetFlag(
            GiftMessageHelper::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ORDER,
            ScopeInterface::SCOPE_STORE
        );
        $items = $this->items($quote);
        if (!$orderAllowed && !$items) {
            return $result;
        }

        $result['gift_message'] = [
            'order_allowed' => $orderAllowed,
            'order' => $orderAllowed ? $this->message($this->cartRepository->get($quoteId)) : null,
            'items' => $items,
            'labels' => [
                'giftOptions' => (string) __('Gift options'),
                'giftMessage' => (string) __('Gift Message'),
                'to' => (string) __('To:'),
                'from' => (string) __('From:'),
                'message' => (string) __('Message:'),
                'update' => (string) __('Update'),
                'cancel' => (string) __('Cancel'),
                'edit' => (string) __('Edit'),
                'delete' => (string) __('Delete'),
                'error' => (string) __('Something went wrong. Please try again.'),
            ],
        ];

        return $result;
    }

    /**
     * The items that may carry a message, with the one they carry
     *
     * @param Quote $quote
     * @return array<int,array<string,mixed>>
     */
    private function items(Quote $quote): array
    {
        $itemsAllowed = $this->scopeConfig->isSetFlag(
            GiftMessageHelper::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ITEMS,
            ScopeInterface::SCOPE_STORE
        );

        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            // The product's own setting unless it uses the config
            $available = $item->getProduct()->getGiftMessageAvailable();
            $allowed = $available !== null && (int) $available !== Boolean::VALUE_USE_CONFIG
                ? (bool) $available
                : $itemsAllowed;
            if ($allowed) {
                $items[] = [
                    'item_id' => (int) $item->getItemId(),
                    'message' => $this->message(
                        $this->itemRepository->get((int) $quote->getId(), (int) $item->getItemId())
                    ),
                ];
            }
        }

        return $items;
    }

    /**
     * A message as the blocks read it, null when there is none
     *
     * @param MessageInterface|null $message
     * @return array<string, string>|null
     */
    private function message(?MessageInterface $message): ?array
    {
        return $message === null ? null : [
            'sender' => (string) $message->getSender(),
            'recipient' => (string) $message->getRecipient(),
            'message' => (string) $message->getMessage(),
        ];
    }
}
