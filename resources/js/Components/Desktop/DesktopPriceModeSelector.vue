<script setup>
defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    showLabel: {
        type: Boolean,
        default: true,
    },
});

defineEmits(['select']);
</script>

<template>
    <fieldset class="desk-source-selector">
        <legend class="sr-only">Source de prix</legend>
        <span v-if="showLabel" class="desk-source-selector__label">Source</span>
        <div class="desk-source-selector__buttons">
            <button
                type="button"
                class="desk-source-selector__button"
                :class="modelValue !== 'personal' ? 'desk-source-selector__button--active' : ''"
                :aria-pressed="modelValue !== 'personal'"
                :disabled="disabled"
                title="Prix HDV communautaire"
                @click="$emit('select', 'community')"
            >
                HDV
            </button>
            <button
                type="button"
                class="desk-source-selector__button"
                :class="modelValue === 'personal' ? 'desk-source-selector__button--active' : ''"
                :aria-pressed="modelValue === 'personal'"
                :disabled="disabled"
                title="Prix personnel privé"
                @click="$emit('select', 'personal')"
            >
                Perso
            </button>
        </div>
    </fieldset>
</template>

<style scoped>
.desk-source-selector {
    display: inline-flex;
    flex-shrink: 0;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.desk-source-selector__label {
    color: #5f6368;
    font-size: 8px;
    font-weight: 800;
    letter-spacing: .04em;
    line-height: 1;
    text-transform: uppercase;
}

.desk-source-selector__buttons {
    display: inline-flex;
    border-top: 1px solid #5d5d5d;
    border-left: 1px solid #5d5d5d;
    border-right: 1px solid white;
    border-bottom: 1px solid white;
    background: #bdb9aa;
    padding: 1px;
}

.desk-source-selector__button {
    min-width: 38px;
    height: 18px;
    border-top: 1px solid white;
    border-left: 1px solid white;
    border-right: 1px solid #6f6b60;
    border-bottom: 1px solid #6f6b60;
    background: linear-gradient(#fff, #d8d4c5);
    padding: 0 5px;
    color: #222;
    font-size: 9px;
    font-weight: 900;
    line-height: 1;
}

.desk-source-selector__button + .desk-source-selector__button {
    margin-left: 1px;
}

.desk-source-selector__button:hover:not(:disabled, .desk-source-selector__button--active) {
    background: #fff;
}

.desk-source-selector__button:focus-visible {
    outline: 1px dotted #111;
    outline-offset: -3px;
}

.desk-source-selector__button--active {
    border-top-color: #16365c;
    border-left-color: #16365c;
    border-right-color: #dcecff;
    border-bottom-color: #dcecff;
    background: #0b4c95;
    color: white;
    box-shadow: inset 1px 1px 0 rgb(0 0 0 / 28%);
}

.desk-source-selector__button:disabled {
    color: #777;
    cursor: wait;
}

.desk-source-selector__button--active:disabled {
    color: #e5edf7;
}
</style>
