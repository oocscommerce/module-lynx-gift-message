<script setup lang="ts">
import { computed, reactive, ref } from 'vue'

// A cart line's gift message, saved through core's quote REST endpoint
const props = defineProps<{ context: Lynx.CartItem; parent: Lynx.CheckoutCart }>()

const TRIGGER =
    'inline-flex items-center gap-1.5 justify-self-start text-base font-semibold text-base-content hover:underline underline-offset-4 bg-transparent border-none p-0 cursor-pointer'
const LINK =
    'text-sm font-semibold text-base-content/70 hover:text-base-content hover:underline underline-offset-4 bg-transparent border-none p-0 cursor-pointer disabled:opacity-60'

const entry = computed(() => props.parent.gift_message?.items.find(item => item.item_id === props.context.item_id))
const labels = computed(() => props.parent.gift_message?.labels ?? {})

const saved = ref<Lynx.GiftMessageText | null>(entry.value?.message ?? null)
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
        const response = await fetch(`${props.parent.cart_api_url}/gift-message/${props.context.item_id}`, {
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
    <div v-if="entry" class="grid gap-3 text-base">
        <button type="button" :aria-expanded="open" :class="TRIGGER" @click="toggle">
            {{ labels.giftOptions }}
            <svg
                class="h-4 w-4 transition-transform"
                :class="open ? 'rotate-180' : ''"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="m6 9 6 6 6-6" />
            </svg>
        </button>

        <template v-if="open">
            <form v-if="editing" class="grid gap-3 max-w-md" @submit.prevent="send({ ...form })">
                <p class="font-semibold text-base-content">{{ labels.giftMessage }}</p>
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
        </template>

        <p v-if="error" role="alert" class="text-sm font-semibold text-error">{{ error }}</p>
    </div>
</template>
