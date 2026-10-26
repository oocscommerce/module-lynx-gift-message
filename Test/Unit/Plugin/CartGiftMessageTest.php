<?php
/**
 * Copyright © OOCS Commerce (https://www.oocscommerce.com). All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace OOCS\LynxGiftMessage\Test\Unit\Plugin;

use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\GiftMessage\Api\CartRepositoryInterface;
use Magento\GiftMessage\Api\Data\MessageInterface;
use Magento\GiftMessage\Api\ItemRepositoryInterface;
use Magento\GiftMessage\Helper\Message as GiftMessageHelper;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use OOCS\LynxGiftMessage\Plugin\CartGiftMessage;
use OOCS\LynxThemeComponent\Provider\Checkout\Cart;
use PHPUnit\Framework\TestCase;

/**
 * The cart's gift messages follow core's rules: order level on a shipping quote, item level by product or config
 */
class CartGiftMessageTest extends TestCase
{
    /**
     * The item that carries a message
     */
    private const MESSAGE_ITEM = 12;

    /**
     * Both levels off and no product turning it on: the props are left alone
     *
     * @return void
     */
    public function testOffLeavesPropsAlone(): void
    {
        $result = $this->plugin(false, false, false, [[11, null]])
            ->afterResolve($this->createMock(Cart::class), ['items' => []]);

        $this->assertSame(['items' => []], $result);
    }

    /**
     * Order level on; item 11 uses the config (off), item 12 turns it on for itself and has a message
     *
     * @return void
     */
    public function testOrderAndProductLevel(): void
    {
        $result = $this->plugin(true, false, false, [[11, '2'], [12, '1']])
            ->afterResolve($this->createMock(Cart::class), []);

        $gift = $result['gift_message'];
        $this->assertTrue($gift['order_allowed']);
        $this->assertSame(['sender' => 'Ann', 'recipient' => 'Bob', 'message' => 'Order'], $gift['order']);
        $this->assertSame(
            [['item_id' => 12, 'message' => ['sender' => 'Ann', 'recipient' => 'Bob', 'message' => 'Item 12']]],
            $gift['items']
        );
        $this->assertSame('Gift options', $gift['labels']['giftOptions']);
    }

    /**
     * A virtual quote has no order-level message; items follow the item-level config
     *
     * @return void
     */
    public function testVirtualQuote(): void
    {
        $result = $this->plugin(true, true, true, [[11, null]])
            ->afterResolve($this->createMock(Cart::class), []);

        $this->assertFalse($result['gift_message']['order_allowed']);
        $this->assertNull($result['gift_message']['order']);
        $this->assertSame([11], array_column($result['gift_message']['items'], 'item_id'));
    }

    /**
     * The plugin over quote 5 holding the items given as [id, gift_message_available]
     *
     * @param bool $order
     * @param bool $items
     * @param bool $virtual
     * @param array $lines
     * @return CartGiftMessage
     */
    private function plugin(bool $order, bool $items, bool $virtual, array $lines): CartGiftMessage
    {
        $quoteItems = [];
        foreach ($lines as [$id, $available]) {
            $product = $this->createMock(Product::class);
            $product->method('getGiftMessageAvailable')->willReturn($available);
            $item = $this->createMock(Item::class);
            $item->method('getItemId')->willReturn($id);
            $item->method('getProduct')->willReturn($product);
            $quoteItems[] = $item;
        }

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(5);
        $quote->method('getItemsCount')->willReturn(\count($lines));
        $quote->method('isVirtual')->willReturn($virtual);
        $quote->method('getAllVisibleItems')->willReturn($quoteItems);
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($quote);

        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('isSetFlag')->willReturnCallback(
            fn (string $path) => $path === GiftMessageHelper::XPATH_CONFIG_GIFT_MESSAGE_ALLOW_ORDER ? $order : $items
        );

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('get')->with(5)->willReturn($this->message('Order'));
        $itemRepository = $this->createMock(ItemRepositoryInterface::class);
        $itemRepository->method('get')
            ->willReturnCallback(fn (...$args) => $args[1] === self::MESSAGE_ITEM ? $this->message('Item 12') : null);

        return new CartGiftMessage($session, $config, $cartRepository, $itemRepository);
    }

    /**
     * A message from Ann to Bob
     *
     * @param string $text
     * @return MessageInterface
     */
    private function message(string $text): MessageInterface
    {
        $message = $this->createMock(MessageInterface::class);
        $message->method('getSender')->willReturn('Ann');
        $message->method('getRecipient')->willReturn('Bob');
        $message->method('getMessage')->willReturn($text);

        return $message;
    }
}
