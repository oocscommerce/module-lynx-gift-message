<script setup lang="ts">
import { computed, reactive, ref } from 'vue'

// The order's gift message under the cart items, saved through core's quote REST endpoint
const props = defineProps<{ parent: Lynx.CheckoutCart }>()

const TRIGGER = 'l-collapse-title text-start text-lg font-semibold'
const LINK =
    'text-sm font-semibold text-base-content/70 hover:text-base-content hover:underline underline-offset-4 bg-transparent border-none p-0 cursor-pointer disabled:opacity-60'

const entry = computed(() => (props.parent.gift_message?.order_allowed ? props.parent.gift_message : null))
const labels = computed(() => props.parent.gift_message?.labels ?? {})

const saved = ref<Lynx.GiftMessageText | null>(entry.value?.order ?? null)
const open = ref(false)
const editing = ref(false)
const busy = ref(false)
const error = ref('')
const form = reactive({ recipient: '', sender: '', message: '' })

function edit() {
    Object.assign(form, saved.value ?? { recipient: '', sender: '', message: '' })
    error.value = ''
    editing.value = true
}

/** Collapse, or expand on the message, or on the form when there is none yet */
function toggle() {
    open.value = !open.value
    if (open.value && !saved.value) edit()
    else editing.value = false
}

function cancel() {
    editing.value = false
    if (!saved.value) open.value = false
}

/** Save the form, or remove the message with null */
async function send(message: Lynx.GiftMessageText | null) {
    busy.value = true
    error.value = ''
    try {
        const response = await fetch(`${props.parent.cart_api_url}/gift-message`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ gift_message: message ?? { sender: null, recipient: null, message: null } }),
        })
        if (!response.ok) throw new Error(String((await response.json().catch(() => null))?.message ?? ''))
        saved.value = message
        editing.value = false
        open.value = message !== null
    } catch (e) {
        error.value = (e as Error).message || (labels.value.error ?? '')
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <!-- A row of the cart's box under the items: the box draws the border -->
    <div v-if="entry" class="l-collapse l-collapse-arrow rounded-none text-base" :class="open ? 'l-collapse-open' : ''">
        <button type="button" :aria-expanded="open" :class="TRIGGER" @click="toggle">
            {{ labels.giftOptions }}
        </button>

        <div v-if="open" class="l-collapse-content grid gap-3">
            <form v-if="editing" class="grid gap-3 max-w-md" @submit.prevent="send({ ...form })">
                <p class="text-lg font-semibold text-base-content">{{ labels.giftMessage }}</p>
                <label class="grid gap-1">
                    <span class="text-sm text-base-content/70">{{ labels.to }}</span>
                    <input v-model="form.recipient" type="text" class="l-input w-full" />
                </label>
                <label class="grid gap-1">
                    <span class="text-sm text-base-content/70">{{ labels.from }}</span>
                    <input v-model="form.sender" type="text" class="l-input w-full" />
                </label>
                <label class="grid gap-1">
                    <span class="text-sm text-base-content/70">{{ labels.message }}</span>
                    <textarea v-model="form.message" rows="3" class="l-textarea w-full" />
                </label>
                <div class="flex items-center gap-4">
                    <button type="submit" :disabled="busy" class="l-btn l-btn-primary px-6">{{ labels.update }}</button>
                    <button type="button" :class="LINK" @click="cancel">{{ labels.cancel }}</button>
                </div>
            </form>

            <div v-else-if="saved" class="grid gap-1">
                <p class="text-sm text-base-content/70">
                    {{ labels.to }} <span class="text-base-content">{{ saved.recipient }}</span>
                    <span aria-hidden="true" class="mx-1 text-base-content/40">·</span>
                    {{ labels.from }} <span class="text-base-content">{{ saved.sender }}</span>
                </p>
                <p class="text-base-content whitespace-pre-line">{{ saved.message }}</p>
                <div class="flex items-center gap-4">
                    <button type="button" :class="LINK" @click="edit">{{ labels.edit }}</button>
                    <button type="button" :disabled="busy" :class="LINK" @click="send(null)">
                        {{ labels.delete }}
                    </button>
                </div>
            </div>
            <p v-if="error" role="alert" class="text-sm font-semibold text-error">{{ error }}</p>
        </div>
    </div>
</template>
