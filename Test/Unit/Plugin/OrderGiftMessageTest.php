<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxGiftMessage\Test\Unit\Plugin;

use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\GiftMessage\Helper\Message as GiftMessageHelper;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use OOCS\LynxCustomer\Provider\Customer\OrderView;
use OOCS\LynxGiftMessage\Plugin\OrderGiftMessage;
use PHPUnit\Framework\TestCase;

/**
 * The order tab shows the messages the order carries and the store allows; document tabs show none
 */
class OrderGiftMessageTest extends TestCase
{
    /**
     * The order's message and item 21's; item 22 carries none
     *
     * @return void
     */
    public function testMessages(): void
    {
        $result = $this->plugin()->afterResolve($this->createMock(OrderView::class), ['view' => 'view']);

        $this->assertSame(
            ['sender' => 'Ann', 'recipient' => 'Bob', 'message' => 'Order'],
            $result['gift_message']['order']
        );
        $this->assertSame([21], array_column($result['gift_message']['items'], 'item_id'));
        $this->assertSame('Gift Message for This Order', $result['gift_message']['labels']['orderMessage']);
    }

    /**
     * An invoice tab is left alone, and so is the sample of one
     *
     * @return void
     */
    public function testDocumentTab(): void
    {
        $plugin = $this->plugin();
        $view = $this->createMock(OrderView::class);

        $this->assertSame(['view' => 'invoice'], $plugin->afterResolve($view, ['view' => 'invoice']));
        $this->assertSame(['view' => 'invoice'], $plugin->afterResolveSample($view, ['view' => 'invoice']));
        $sample = $plugin->afterResolveSample($view, ['view' => 'view'])['gift_message'];
        $this->assertSame(1, $sample['items'][0]['item_id']);
    }

    /**
     * The plugin over order 7 with a message, item 21 with one and item 22 without
     *
     * @return OrderGiftMessage
     */
    private function plugin(): OrderGiftMessage
    {
        $items = [];
        foreach ([21 => 3, 22 => null] as $id => $messageId) {
            $item = $this->createMock(Item::class);
            $item->method('getItemId')->willReturn($id);
            $item->method('__call')->willReturnMap([['getGiftMessageId', [], $messageId]]);
            $items[] = $item;
        }
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(7);
        $order->method('__call')->willReturnMap([['getGiftMessageId', [], 2]]);
        $order->method('getAllVisibleItems')->willReturn($items);

        $registry = $this->createMock(Registry::class);
        $registry->method('registry')->with('current_order')->willReturn($order);

        $helper = $this->createMock(GiftMessageHelper::class);
        $helper->method('isMessagesAllowed')->willReturn(true);
        $helper->method('getGiftMessageForEntity')->willReturnCallback(
            fn (DataObject $entity) => new DataObject([
                'sender' => 'Ann',
                'recipient' => 'Bob',
                'message' => $entity === $order ? 'Order' : 'Item',
            ])
        );

        return new OrderGiftMessage($registry, $helper);
    }
}
