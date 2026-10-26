import { mountNode } from '@oocs/module-lynx-theme/test'
import { flushPromises } from '@vue/test-utils'
import CartItemGiftMessage from 'OOCS_LynxGiftMessage/Components/CartItemGiftMessage.vue'
import OrderItemGiftMessage from 'OOCS_LynxGiftMessage/Components/OrderItemGiftMessage.vue'
import { afterEach, describe, expect, it, vi } from 'vitest'

const LABELS = { giftOptions: 'Gift options', update: 'Update', edit: 'Edit', delete: 'Delete', error: 'Failed' }

const cart = (message: Lynx.GiftMessageText | null = null) =>
    ({
        cart_api_url: '/rest/V1/guest-carts/abc',
        gift_message: { order_allowed: false, order: null, items: [{ item_id: 7, message }], labels: LABELS },
    }) as unknown as Lynx.CheckoutCart

describe('CartItemGiftMessage', () => {
    afterEach(() => vi.unstubAllGlobals())

    it('renders nothing for an item that may not carry a message', () => {
        const wrapper = mountNode(CartItemGiftMessage, { props: { context: { item_id: 8 }, parent: cart() } })

        expect(wrapper.text()).toBe('')
    })

    it('saves the form to the item endpoint and shows the message', async () => {
        const fetchMock = vi.fn().mockResolvedValue(new Response('true'))
        vi.stubGlobal('fetch', fetchMock)
        const wrapper = mountNode(CartItemGiftMessage, { props: { context: { item_id: 7 }, parent: cart() } })

        await wrapper.find('button').trigger('click')
        const [to, from] = wrapper.findAll('input')
        await to.setValue('Bob')
        await from.setValue('Ann')
        await wrapper.find('textarea').setValue('Hi')
        await wrapper.find('form').trigger('submit')
        await flushPromises()

        expect(fetchMock).toHaveBeenCalledWith('/rest/V1/guest-carts/abc/gift-message/7', expect.anything())
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
            gift_message: { recipient: 'Bob', sender: 'Ann', message: 'Hi' },
        })
        expect(wrapper.text()).toContain('Hi')
    })

    it("shows Magento's message when the save is refused", async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('{"message":"Refused"}', { status: 400 })))
        const wrapper = mountNode(CartItemGiftMessage, {
            props: { context: { item_id: 7 }, parent: cart({ sender: 'A', recipient: 'B', message: 'Old' }) },
        })

        await wrapper.find('button').trigger('click')
        await wrapper.findAll('button')[2].trigger('click')
        await flushPromises()

        expect(wrapper.find('[role="alert"]').text()).toBe('Refused')
        expect(wrapper.text()).toContain('Old')
    })
})

describe('CartItemGiftMessage toggle', () => {
    it('opens on the saved message and closes again from its heading', async () => {
        const wrapper = mountNode(CartItemGiftMessage, {
            props: { context: { item_id: 7 }, parent: cart({ sender: 'A', recipient: 'B', message: 'Saved' }) },
        })
        const heading = wrapper.find('button')

        expect(heading.attributes('aria-expanded')).toBe('false')
        expect(wrapper.text()).not.toContain('Saved')
        await heading.trigger('click')
        expect(heading.attributes('aria-expanded')).toBe('true')
        expect(wrapper.text()).toContain('Saved')
        expect(wrapper.find('form').exists()).toBe(false)
        await heading.trigger('click')
        expect(wrapper.text()).not.toContain('Saved')
    })
})

describe('OrderItemGiftMessage', () => {
    it("shows the item's message, nothing for an item without one", () => {
        const parent = {
            gift_message: {
                order: null,
                items: [{ item_id: 3, message: { sender: 'Ann', recipient: 'Bob', message: 'Enjoy' } }],
                labels: { itemMessage: 'Gift Message' },
            },
        } as unknown as Lynx.AccountOrderView

        expect(mountNode(OrderItemGiftMessage, { props: { context: { id: 3 }, parent } }).text()).toContain('Enjoy')
        expect(mountNode(OrderItemGiftMessage, { props: { context: { id: 4 }, parent } }).text()).toBe('')
    })
})
