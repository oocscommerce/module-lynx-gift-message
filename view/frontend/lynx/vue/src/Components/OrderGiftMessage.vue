<script setup lang="ts">
import { computed } from 'vue'

// The order's gift message, under the items
const props = defineProps<{ parent: Lynx.AccountOrderView }>()

const message = computed(() => props.parent.gift_message?.order)
const labels = computed(() => props.parent.gift_message?.labels ?? {})
</script>

<template>
    <div
        v-if="message"
        class="border-(length:--border) border-solid border-base-300 rounded-box p-5 grid gap-2 break-inside-avoid"
    >
        <h3 class="text-base font-semibold text-base-content">{{ labels.orderMessage }}</h3>
        <p class="text-sm text-base-content/70">
            {{ labels.from }} <span class="text-base-content">{{ message.sender }}</span>
            <span aria-hidden="true" class="mx-1 text-base-content/40">·</span>
            {{ labels.to }} <span class="text-base-content">{{ message.recipient }}</span>
        </p>
        <p class="text-sm text-base-content whitespace-pre-line">{{ message.message }}</p>
    </div>
</template>
