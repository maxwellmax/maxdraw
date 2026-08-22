<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-[12.5px] text-sd-crit">
            {{ message }}
        </p>
    </div>
</template>
