<script setup lang="ts">
import {
    KeyRound,
    Lock,
    ShieldCheck,
    ShieldAlert,
    Sparkles,
} from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useVault } from '@/composables/useVault';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'unlocked'): void;
}>();

const { isConfigured, isUnlocked, unlock, setupVault, isTeamVault, teamName } =
    useVault();

const masterPassword = ref('');
const confirmPassword = ref('');
const errorMessage = ref('');
const isProcessing = ref(false);

async function handleUnlock() {
    if (!masterPassword.value) {
        errorMessage.value = 'Please enter your Master Password.';
        return;
    }
    errorMessage.value = '';
    isProcessing.value = true;
    try {
        const success = await unlock(masterPassword.value);
        if (success) {
            masterPassword.value = '';
            emit('unlocked');
        } else {
            errorMessage.value = 'Incorrect Master Password. Please try again.';
        }
    } finally {
        isProcessing.value = false;
    }
}

async function handleSetup() {
    if (!masterPassword.value) {
        errorMessage.value = 'Master Password cannot be empty.';
        return;
    }
    if (masterPassword.value.length < 8) {
        errorMessage.value =
            'Master Password should be at least 8 characters long.';
        return;
    }
    if (masterPassword.value !== confirmPassword.value) {
        errorMessage.value = 'Passwords do not match.';
        return;
    }

    errorMessage.value = '';
    isProcessing.value = true;
    try {
        const success = await setupVault(masterPassword.value);
        if (success) {
            masterPassword.value = '';
            confirmPassword.value = '';
            emit('unlocked');
        }
    } finally {
        isProcessing.value = false;
    }
}
</script>

<template>
    <Dialog :open="open && !isUnlocked">
        <DialogContent class="sm:max-w-md [&>button]:hidden" :trapFocus="true">
            <!-- SETUP MODE -->
            <template v-if="!isConfigured">
                <DialogHeader class="text-center sm:text-left">
                    <div
                        class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary sm:mx-0"
                    >
                        <Sparkles class="h-6 w-6" />
                    </div>
                    <DialogTitle class="text-xl font-bold tracking-tight"
                        >Initialize Your Secure Vault</DialogTitle
                    >
                    <DialogDescription
                        class="pt-1 text-sm text-muted-foreground"
                    >
                        We use zero-knowledge WebCrypto (AES-256-GCM + PBKDF2).
                        Your Master Password will encrypt all data in your
                        browser. Our servers will never see or store your plain
                        passwords.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleSetup" class="space-y-4 py-2">
                    <div
                        v-if="errorMessage"
                        class="flex items-center gap-2 rounded-lg bg-destructive/10 p-3 text-sm text-destructive"
                    >
                        <ShieldAlert class="h-4 w-4 shrink-0" />
                        <span>{{ errorMessage }}</span>
                    </div>

                    <div class="space-y-2">
                        <Label for="setup-master-password"
                            >Create Master Password</Label
                        >
                        <Input
                            id="setup-master-password"
                            v-model="masterPassword"
                            type="password"
                            placeholder="Choose a strong master passphrase"
                            autocomplete="new-password"
                            required
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="setup-confirm-password"
                            >Confirm Master Password</Label
                        >
                        <Input
                            id="setup-confirm-password"
                            v-model="confirmPassword"
                            type="password"
                            placeholder="Confirm your master passphrase"
                            autocomplete="new-password"
                            required
                        />
                    </div>

                    <div
                        class="space-y-1 rounded-lg border border-primary/20 bg-primary/5 p-3 text-xs text-muted-foreground"
                    >
                        <p
                            class="flex items-center gap-1.5 font-medium text-foreground"
                        >
                            <ShieldCheck class="h-4 w-4 text-emerald-500" />
                            Zero-Knowledge Guarantee
                        </p>
                        <p>
                            If you forget your master password, your vault
                            cannot be recovered. Make sure to keep it safe.
                        </p>
                    </div>

                    <DialogFooter class="pt-2">
                        <Button
                            type="submit"
                            class="w-full sm:w-auto"
                            :disabled="isProcessing"
                        >
                            <Spinner v-if="isProcessing" class="mr-2" />
                            Generate Keys & Setup Vault
                        </Button>
                    </DialogFooter>
                </form>
            </template>

            <!-- UNLOCK MODE -->
            <template v-else>
                <DialogHeader class="text-center sm:text-left">
                    <div
                        class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary sm:mx-0"
                    >
                        <Lock class="h-6 w-6" />
                    </div>
                    <DialogTitle class="text-xl font-bold tracking-tight">
                        Unlock
                        {{ isTeamVault ? teamName : 'Your Personal' }} Vault
                    </DialogTitle>
                    <DialogDescription
                        class="pt-1 text-sm text-muted-foreground"
                    >
                        Enter your Master Password to decrypt your credentials
                        for this session.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleUnlock" class="space-y-4 py-2">
                    <div
                        v-if="errorMessage"
                        class="flex items-center gap-2 rounded-lg bg-destructive/10 p-3 text-sm text-destructive"
                    >
                        <ShieldAlert class="h-4 w-4 shrink-0" />
                        <span>{{ errorMessage }}</span>
                    </div>

                    <div class="space-y-2">
                        <Label for="unlock-master-password"
                            >Master Password</Label
                        >
                        <div class="relative">
                            <Input
                                id="unlock-master-password"
                                v-model="masterPassword"
                                type="password"
                                placeholder="Enter master password"
                                autocomplete="current-password"
                                class="pr-10"
                                autofocus
                                required
                            />
                            <div
                                class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground"
                            >
                                <KeyRound class="h-4 w-4" />
                            </div>
                        </div>
                    </div>

                    <DialogFooter class="pt-2">
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="isProcessing"
                        >
                            <Spinner v-if="isProcessing" class="mr-2" />
                            Unlock Vault
                        </Button>
                    </DialogFooter>
                </form>
            </template>
        </DialogContent>
    </Dialog>
</template>
