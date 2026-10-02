<script setup lang="ts">
import { Check, Copy, Dices, RefreshCw, Shield, Sparkles } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useVault } from '@/composables/useVault';
import {
    calculatePasswordStrength,
    generatePassword,
    type PasswordGeneratorOptions,
} from '@/lib/crypto';

const props = defineProps<{
    open: boolean;
    standalone?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'select', password: string): void;
}>();

const { copyToClipboardWithAutoClear } = useVault();

const mode = ref<'password' | 'passphrase'>('password');
const length = ref(20);
const uppercase = ref(true);
const lowercase = ref(true);
const numbers = ref(true);
const symbols = ref(true);
const avoidAmbiguous = ref(true);
const wordsCount = ref(4);
const separator = ref('-');

const generatedPassword = ref('');
const isCopied = ref(false);

const strength = computed(() =>
    calculatePasswordStrength(generatedPassword.value),
);

function regenerate() {
    const options: PasswordGeneratorOptions = {
        mode: mode.value,
        length: length.value,
        uppercase: uppercase.value,
        lowercase: lowercase.value,
        numbers: numbers.value,
        symbols: symbols.value,
        avoidAmbiguous: avoidAmbiguous.value,
        wordsCount: wordsCount.value,
        separator: separator.value,
    };
    generatedPassword.value = generatePassword(options);
    isCopied.value = false;
}

watch(
    () => [
        props.open,
        mode.value,
        length.value,
        uppercase.value,
        lowercase.value,
        numbers.value,
        symbols.value,
        avoidAmbiguous.value,
        wordsCount.value,
        separator.value,
    ],
    () => {
        if (props.open && !generatedPassword.value) {
            regenerate();
        } else if (props.open) {
            regenerate();
        }
    },
    { immediate: true },
);

async function handleCopy() {
    if (!generatedPassword.value) return;
    await copyToClipboardWithAutoClear(
        generatedPassword.value,
        'Generated Password',
    );
    isCopied.value = true;
    setTimeout(() => {
        isCopied.value = false;
    }, 2000);
}

function handleSelect() {
    emit('select', generatedPassword.value);
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <div
                    class="mb-1 flex items-center gap-2 text-sm font-semibold text-primary"
                >
                    <Sparkles class="h-4 w-4" />
                    <span>Cryptographic Generator</span>
                </div>
                <DialogTitle class="text-xl font-bold"
                    >Generate Strong Password</DialogTitle
                >
                <DialogDescription>
                    Customizable entropy generator powered by WebCrypto API.
                </DialogDescription>
            </DialogHeader>

            <!-- Mode Selector Tabs -->
            <div
                class="grid grid-cols-2 gap-1 rounded-lg bg-muted p-1 text-sm font-medium"
            >
                <button
                    type="button"
                    class="rounded-md py-1.5 transition-colors"
                    :class="
                        mode === 'password'
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="mode = 'password'"
                >
                    Random Password
                </button>
                <button
                    type="button"
                    class="rounded-md py-1.5 transition-colors"
                    :class="
                        mode === 'passphrase'
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="mode = 'passphrase'"
                >
                    Memorable Passphrase
                </button>
            </div>

            <!-- Password Display Box -->
            <div class="space-y-2">
                <div
                    class="relative flex items-center justify-between rounded-xl border border-input bg-card p-3 shadow-xs"
                >
                    <span
                        class="pr-14 font-mono text-base font-semibold tracking-wide break-all text-foreground select-all"
                    >
                        {{ generatedPassword }}
                    </span>
                    <div class="absolute right-2 flex items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-muted-foreground hover:bg-muted hover:text-foreground"
                            @click="regenerate"
                            title="Regenerate"
                        >
                            <RefreshCw
                                class="h-4 w-4 transition-transform active:rotate-180"
                            />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8 text-muted-foreground hover:bg-muted hover:text-foreground"
                            @click="handleCopy"
                            title="Copy to clipboard"
                        >
                            <Check
                                v-if="isCopied"
                                class="h-4 w-4 text-emerald-500"
                            />
                            <Copy v-else class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <!-- Strength Meter Bar -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between text-xs">
                        <span
                            class="flex items-center gap-1 font-medium"
                            :class="
                                strength.score >= 3
                                    ? 'text-emerald-500'
                                    : 'text-amber-500'
                            "
                        >
                            <Shield class="h-3.5 w-3.5" />
                            {{ strength.label }} ({{ strength.entropy }} bits
                            entropy)
                        </span>
                        <span class="text-[11px] text-muted-foreground"
                            >Crack time: ~{{ strength.crackTime }}</span
                        >
                    </div>
                    <div
                        class="flex h-1.5 w-full gap-1 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full transition-all duration-300"
                            :class="[
                                strength.score === 1
                                    ? 'w-1/4 bg-red-500'
                                    : strength.score === 2
                                      ? 'w-2/4 bg-orange-500'
                                      : strength.score === 3
                                        ? 'w-3/4 bg-yellow-500'
                                        : 'w-full bg-emerald-500',
                            ]"
                        />
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div class="space-y-4 pt-1">
                <!-- Random Password Options -->
                <template v-if="mode === 'password'">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <Label for="length-slider"
                                >Length:
                                <span class="font-bold text-foreground"
                                    >{{ length }} characters</span
                                ></Label
                            >
                        </div>
                        <input
                            id="length-slider"
                            v-model.number="length"
                            type="range"
                            min="8"
                            max="64"
                            class="h-2 w-full cursor-pointer rounded-lg bg-muted accent-primary"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <label
                            class="flex cursor-pointer items-center gap-2 select-none"
                        >
                            <Checkbox v-model="uppercase" />
                            <span>Uppercase (A-Z)</span>
                        </label>
                        <label
                            class="flex cursor-pointer items-center gap-2 select-none"
                        >
                            <Checkbox v-model="lowercase" />
                            <span>Lowercase (a-z)</span>
                        </label>
                        <label
                            class="flex cursor-pointer items-center gap-2 select-none"
                        >
                            <Checkbox v-model="numbers" />
                            <span>Numbers (0-9)</span>
                        </label>
                        <label
                            class="flex cursor-pointer items-center gap-2 select-none"
                        >
                            <Checkbox v-model="symbols" />
                            <span>Symbols (!@#$)</span>
                        </label>
                    </div>

                    <label
                        class="flex cursor-pointer items-center gap-2 pt-1 text-sm text-muted-foreground select-none"
                    >
                        <Checkbox v-model="avoidAmbiguous" />
                        <span
                            >Avoid ambiguous characters (1, l, I, 0, O, o)</span
                        >
                    </label>
                </template>

                <!-- Passphrase Options -->
                <template v-else>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-sm">
                            <Label for="words-slider"
                                >Word Count:
                                <span class="font-bold text-foreground"
                                    >{{ wordsCount }} words</span
                                ></Label
                            >
                        </div>
                        <input
                            id="words-slider"
                            v-model.number="wordsCount"
                            type="range"
                            min="3"
                            max="8"
                            class="h-2 w-full cursor-pointer rounded-lg bg-muted accent-primary"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label class="text-sm">Word Separator</Label>
                        <div class="flex gap-2">
                            <button
                                v-for="sep in ['-', '.', '_', ' ']"
                                :key="sep"
                                type="button"
                                class="flex-1 rounded-md border py-1 text-center font-mono text-sm transition-colors"
                                :class="
                                    separator === sep
                                        ? 'border-primary bg-primary/10 font-bold text-primary'
                                        : 'border-input hover:bg-muted'
                                "
                                @click="separator = sep"
                            >
                                {{ sep === ' ' ? 'Space' : sep }}
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <DialogFooter class="flex flex-col gap-2 pt-2 sm:flex-row">
                <Button
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="handleCopy"
                >
                    <Copy class="mr-2 h-4 w-4" />
                    Copy Password
                </Button>
                <Button
                    v-if="!standalone"
                    class="w-full sm:w-auto"
                    @click="handleSelect"
                >
                    <Check class="mr-2 h-4 w-4" />
                    Use This Password
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
