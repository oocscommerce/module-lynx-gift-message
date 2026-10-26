<script setup lang="ts">
import { computed } from 'vue'

// An order item's gift message, under its name
const props = defineProps<{ context: Lynx.OrderDetailItems; parent: Lynx.AccountOrderView }>()

const entry = computed(() => props.parent.gift_message?.items.find(item => item.item_id === props.context.id))
const labels = computed(() => props.parent.gift_message?.labels ?? {})
</script>

<template>
    <div v-if="entry" class="mt-2 grid gap-0.5 text-sm">
        <p class="font-semibold text-base-content">{{ labels.itemMessage }}</p>
        <p class="text-base-content/70">
            {{ labels.from }} <span class="text-base-content">{{ entry.message.sender }}</span>
            <span aria-hidden="true" class="mx-1 text-base-content/40">·</span>
            {{ labels.to }} <span class="text-base-content">{{ entry.message.recipient }}</span>
        </p>
        <p class="text-base-content whitespace-pre-line">{{ entry.message.message }}</p>
    </div>
</template>
