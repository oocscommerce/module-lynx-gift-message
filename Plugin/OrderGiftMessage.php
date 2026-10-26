<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxGiftMessage\Plugin;

use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\GiftMessage\Helper\Message as GiftMessageHelper;
use Magento\Sales\Model\Order;
use OOCS\LynxCustomer\Provider\Customer\OrderView;

/**
 * The order's gift messages on its order tab, as core's order/view.phtml and order/items.phtml show them
 */
class OrderGiftMessage
{
    /**
     * Registry for the viewed order, gift message helper
     *
     * @param Registry $registry
     * @param GiftMessageHelper $giftMessageHelper
     */
    public function __construct(
        private readonly Registry $registry,
        private readonly GiftMessageHelper $giftMessageHelper,
    ) {
    }

    /**
     * Add the order's message and each item's, those it carries and the store allows
     *
     * @param OrderView $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterResolve(OrderView $subject, array $result): array
    {
        /** @var Order|null $order */
        $order = $this->registry->registry('current_order');
        // Invoices, shipments and refunds show no messages
        if (($result['view'] ?? '') !== 'view' || !$order || !$order->getId()) {
            return $result;
        }

        $messages = $this->messages($order);
        if ($messages['order'] === null && !$messages['items']) {
            return $result;
        }

        $result['gift_message'] = $messages + ['labels' => $this->labels()];

        return $result;
    }

    /**
     * The sample order with a message of its own and one on its first item
     *
     * @param OrderView $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterResolveSample(OrderView $subject, array $result): array
    {
        if (($result['view'] ?? '') !== 'view') {
            return $result;
        }

        $result['gift_message'] = [
            'order' => ['sender' => 'Sample Customer', 'recipient' => 'Sample Friend', 'message' => 'Happy birthday!'],
            'items' => [
                [
                    'item_id' => 1,
                    'message' => ['sender' => 'Sample Customer', 'recipient' => 'Sample Friend', 'message' => 'Enjoy!'],
                ],
            ],
            'labels' => $this->labels(),
        ];

        return $result;
    }

    /**
     * The order's message and its items', those it carries and the store allows
     *
     * @param Order $order
     * @return array{order:?array,items:array}
     */
    private function messages(Order $order): array
    {
        $messages = ['order' => null, 'items' => []];
        if ($this->giftMessageHelper->isMessagesAllowed('order', $order) && $order->getGiftMessageId()) {
            $messages['order'] = $this->message($this->giftMessageHelper->getGiftMessageForEntity($order));
        }
        foreach ($order->getAllVisibleItems() as $item) {
            if ($this->giftMessageHelper->isMessagesAllowed('order_item', $item) && $item->getGiftMessageId()) {
                $messages['items'][] = [
                    'item_id' => (int) $item->getItemId(),
                    'message' => $this->message($this->giftMessageHelper->getGiftMessageForEntity($item)),
                ];
            }
        }

        return $messages;
    }

    /**
     * The strings core's order page shows around the messages
     *
     * @return array<string, string>
     */
    private function labels(): array
    {
        return [
            'orderMessage' => (string) __('Gift Message for This Order'),
            'itemMessage' => (string) __('Gift Message'),
            'from' => (string) __('From'),
            'to' => (string) __('To'),
        ];
    }

    /**
     * A message as the blocks read it
     *
     * @param DataObject $message
     * @return array<string, string>
     */
    private function message(DataObject $message): array
    {
        return [
            'sender' => (string) $message->getSender(),
            'recipient' => (string) $message->getRecipient(),
            'message' => (string) $message->getMessage(),
        ];
    }
}
