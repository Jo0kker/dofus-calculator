<script setup>
import { computed, ref } from 'vue';
import DesktopAppShell from '@/Components/Desktop/Apps/DesktopAppShell.vue';

const display = ref('0');
const result = ref(null);
const error = ref('');

const buttons = [
    ['7', '8', '9', '÷'],
    ['4', '5', '6', '×'],
    ['1', '2', '3', '-'],
    ['0', '.', 'C', '+'],
];

const expression = computed(() => display.value.replace(/×/g, '*').replace(/÷/g, '/'));

const append = (value) => {
    error.value = '';

    if (value === 'C') {
        display.value = '0';
        result.value = null;
        return;
    }

    if (display.value === '0' && /[0-9.]/.test(value)) {
        display.value = value;
        return;
    }

    display.value += value;
};

const backspace = () => {
    error.value = '';
    display.value = display.value.length > 1 ? display.value.slice(0, -1) : '0';
};

const parseExpression = (tokens) => {
    let i = 0;

    const parseExpr = () => {
        let left = parseTerm();
        while (i < tokens.length && (tokens[i] === '+' || tokens[i] === '-')) {
            const op = tokens[i++];
            const right = parseTerm();
            left = op === '+' ? left + right : left - right;
        }
        return left;
    };

    const parseTerm = () => {
        let left = parseFactor();
        while (i < tokens.length && (tokens[i] === '*' || tokens[i] === '/')) {
            const op = tokens[i++];
            const right = parseFactor();
            if (op === '/' && right === 0) throw new Error('Division par zéro');
            left = op === '*' ? left * right : left / right;
        }
        return left;
    };

    const parseFactor = () => {
        if (tokens[i] === '(') {
            i++;
            const val = parseExpr();
            if (tokens[i] !== ')') throw new Error('Parenthèse manquante');
            i++;
            return val;
        }
        let num = '';
        let hasDot = false;
        if (tokens[i] === '-') { num += '-'; i++; }
        while (i < tokens.length && /[0-9.]/.test(tokens[i])) {
            if (tokens[i] === '.') {
                if (hasDot) throw new Error('Nombre invalide');
                hasDot = true;
            }
            num += tokens[i++];
        }
        if (!num || num === '-') throw new Error('Nombre invalide');
        return parseFloat(num);
    };

    const value = parseExpr();
    if (i !== tokens.length) throw new Error('Calcul invalide');
    return value;
};

const calculate = () => {
    error.value = '';

    const tokens = expression.value.replace(/\s/g, '').split('');

    if (!tokens.length || !/^[0-9+\-*/().]+$/.test(tokens.join(''))) {
        error.value = 'Calcul invalide';
        return;
    }

    try {
        const value = parseExpression(tokens);
        if (!Number.isFinite(value)) {
            error.value = 'Résultat invalide';
            return;
        }
        result.value = Math.round(value * 100) / 100;
        display.value = String(result.value);
    } catch (e) {
        error.value = e.message || 'Calcul invalide';
    }
};

const formatKamas = (value) => new Intl.NumberFormat('fr-FR').format(Number(value || 0));
</script>

<template>
    <DesktopAppShell title="Calculette" subtitle="Pour les calculs rapides de prix, ressources et marges.">
        <div class="calculator">
            <div class="screen">
                <div class="expression">{{ display }}</div>
                <div class="hint">{{ error || (result !== null ? `${formatKamas(result)} K` : 'Entrée en kamas') }}</div>
            </div>

            <div class="grid grid-cols-4 gap-2">
                <template v-for="row in buttons" :key="row.join('-')">
                    <button
                        v-for="button in row"
                        :key="button"
                        type="button"
                        class="calc-button"
                        :class="/[+\-×÷]/.test(button) ? 'operator' : ''"
                        @click="append(button)"
                    >
                        {{ button }}
                    </button>
                </template>
                <button type="button" class="calc-button" @click="append('(')">(</button>
                <button type="button" class="calc-button" @click="append(')')">)</button>
                <button type="button" class="calc-button" @click="backspace">⌫</button>
                <button type="button" class="calc-button equals" @click="calculate">=</button>
            </div>
        </div>
    </DesktopAppShell>
</template>

<style scoped>
.calculator {
    max-width: 20rem;
    margin: 0 auto;
    border: 1px solid #8f8a78;
    background: #d8d2bd;
    padding: 10px;
    box-shadow: inset 1px 1px 0 #fff, 4px 4px 0 rgb(0 0 0 / .16);
}
.screen {
    margin-bottom: 10px;
    border: 1px solid #6f6a5b;
    background: #f9fff0;
    padding: 10px;
    text-align: right;
    box-shadow: inset 2px 2px 0 rgb(0 0 0 / .14);
}
.expression {
    min-height: 2rem;
    overflow-wrap: anywhere;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 1.35rem;
    font-weight: 900;
    color: #102a43;
}
.hint {
    margin-top: 3px;
    min-height: 1rem;
    color: #5f6b7a;
    font-size: .7rem;
    font-weight: 700;
}
.calc-button {
    min-height: 3rem;
    border: 1px solid #555;
    background: linear-gradient(#fff, #cac3b3);
    box-shadow: inset 1px 1px 0 #fff, inset -1px -1px 0 #8a8375;
    font-size: 1rem;
    font-weight: 900;
}
.calc-button:active {
    box-shadow: inset -1px -1px 0 #fff, inset 1px 1px 0 #8a8375;
}
.operator,
.equals {
    background: linear-gradient(#dbeafe, #93c5fd);
    color: #0b3f88;
}
.equals {
    background: linear-gradient(#bbf7d0, #4ade80);
    color: #14532d;
}
</style>
