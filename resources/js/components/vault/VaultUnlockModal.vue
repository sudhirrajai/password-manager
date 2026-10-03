<script setup lang="ts">
import {
    AlertTriangle,
    Check,
    Copy,
    Download,
    Eye,
    EyeOff,
    KeyRound,
    LifeBuoy,
    Lock,
    RefreshCw,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    Trash2,
} from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useVault } from '@/composables/useVault';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'unlocked'): void;
    (e: 'update:open', val: boolean): void;
}>();

const {
    isConfigured,
    isUnlocked,
    hasRecoveryKey,
    unlock,
    setupVault,
    recoverVaultWithKey,
    resetVaultWipe,
    isTeamVault,
    teamName,
} = useVault();

// View modes: 'unlock' | 'setup' | 'display_recovery' | 'recover' | 'wipe_confirm'
const viewMode = ref<'unlock' | 'setup' | 'display_recovery' | 'recover' | 'wipe_confirm'>('unlock');

const masterPassword = ref('');
const confirmPassword = ref('');
const recoveryKeyInput = ref('');
const generatedRecoveryKey = ref('');
const isSavedConfirmed = ref(false);
const isCopied = ref(false);

const errorMessage = ref('');
const isProcessing = ref(false);
const showPassword = ref(false);

// Initialize viewMode depending on whether vault is already configured
if (!isConfigured.value) {
    viewMode.value = 'setup';
}

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
        errorMessage.value = 'Master Password should be at least 8 characters long.';
        return;
    }
    if (masterPassword.value !== confirmPassword.value) {
        errorMessage.value = 'Passwords do not match.';
        return;
    }

    errorMessage.value = '';
    isProcessing.value = true;
    try {
        const result = await setupVault(masterPassword.value);
        if (result.success && result.recoveryKey) {
            generatedRecoveryKey.value = result.recoveryKey;
            viewMode.value = 'display_recovery';
        }
    } finally {
        isProcessing.value = false;
    }
}

async function handleRecover() {
    if (!recoveryKeyInput.value.trim()) {
        errorMessage.value = 'Please enter your 24-character Recovery Key.';
        return;
    }
    if (!masterPassword.value || masterPassword.value.length < 8) {
        errorMessage.value = 'New Master Password must be at least 8 characters long.';
        return;
    }
    if (masterPassword.value !== confirmPassword.value) {
        errorMessage.value = 'New passwords do not match.';
        return;
    }

    errorMessage.value = '';
    isProcessing.value = true;
    try {
        const result = await recoverVaultWithKey(recoveryKeyInput.value, masterPassword.value);
        if (result.success && result.newRecoveryKey) {
            generatedRecoveryKey.value = result.newRecoveryKey;
            viewMode.value = 'display_recovery';
        }
    } finally {
        isProcessing.value = false;
    }
}

async function handleWipeVault() {
    isProcessing.value = true;
    try {
        const ok = await resetVaultWipe();
        if (ok) {
            masterPassword.value = '';
            confirmPassword.value = '';
            recoveryKeyInput.value = '';
            viewMode.value = 'setup';
        }
    } finally {
        isProcessing.value = false;
    }
}

function handleCopyRecoveryKey() {
    navigator.clipboard.writeText(generatedRecoveryKey.value);
    isCopied.value = true;
    toast.success('Recovery Key copied to clipboard!');
    setTimeout(() => {
        isCopied.value = false;
    }, 2000);
}

function handleDownloadRecoveryKit() {
    const content = `VAULTGUARD EMERGENCY RECOVERY KIT
===========================================
Date: ${new Date().toLocaleString()}
Recovery Key: ${generatedRecoveryKey.value}

INSTRUCTIONS:
1. Store this recovery key in a secure location (e.g. encrypted drive or printed in a safe).
2. If you ever forget your Master Password, this key will restore your access without losing any credentials.
3. This recovery key is NEVER stored on our servers.
===========================================`;

    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `vaultguard-recovery-key-${new Date().toISOString().slice(0, 10)}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    toast.success('Recovery Kit downloaded!');
}

function finishSetup() {
    masterPassword.value = '';
    confirmPassword.value = '';
    generatedRecoveryKey.value = '';
    isSavedConfirmed.value = false;
    viewMode.value = 'unlock';
    emit('unlocked');
}
</script>

<template>
    <Dialog :open="open && !isUnlocked" @update:open="(val) => emit('update:open', val)">
        <DialogContent
            class="w-full max-w-[calc(100%-2rem)] sm:max-w-md rounded-2xl border border-border bg-card p-6 shadow-xl overflow-hidden"
            :showCloseButton="true"
            :trapFocus="true"
        >
            <!-- 1. DISPLAY RECOVERY KEY (ONCE ONLY) -->
            <template v-if="viewMode === 'display_recovery'">
                <DialogHeader class="text-left space-y-2">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-500/10 text-emerald-500"
                    >
                        <ShieldCheck class="h-6 w-6" />
                    </div>
                    <DialogTitle class="text-xl font-bold tracking-tight">
                        Save Your Emergency Recovery Key
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground leading-relaxed">
                        This 24-character master code is your ultimate safety net. <strong>It will only be shown once.</strong> If you ever forget your Master Password, this key will unlock ALL your vaults and restore access without losing any data.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 pt-2">
                    <div
                        class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-center space-y-1.5"
                    >
                        <span class="text-[11px] uppercase tracking-wider font-semibold text-emerald-600 dark:text-emerald-400">
                            Emergency Recovery Key
                        </span>
                        <div
                            class="font-mono text-lg sm:text-xl font-bold tracking-wider text-foreground select-all break-all py-1"
                        >
                            {{ generatedRecoveryKey }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="gap-1.5 w-full text-xs"
                            @click="handleCopyRecoveryKey"
                        >
                            <Check v-if="isCopied" class="h-3.5 w-3.5 text-emerald-500" />
                            <Copy v-else class="h-3.5 w-3.5" />
                            <span>{{ isCopied ? 'Copied!' : 'Copy Key' }}</span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="gap-1.5 w-full text-xs border-primary/30 text-primary hover:bg-primary/10"
                            @click="handleDownloadRecoveryKit"
                        >
                            <Download class="h-3.5 w-3.5" />
                            <span>Download .txt</span>
                        </Button>
                    </div>

                    <label
                        class="flex items-start gap-2.5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 cursor-pointer select-none text-xs text-amber-700 dark:text-amber-300"
                    >
                        <Checkbox v-model="isSavedConfirmed" class="mt-0.5 shrink-0" />
                        <span>I have saved this recovery key safely. I understand that without this key or my master password, my vault cannot be recovered.</span>
                    </label>

                    <div class="pt-2">
                        <Button
                            class="w-full h-10 font-semibold"
                            :disabled="!isSavedConfirmed"
                            @click="finishSetup"
                        >
                            Confirm & Enter Vault
                        </Button>
                    </div>
                </div>
            </template>

            <!-- 2. RECOVERY KEY UNLOCK MODE -->
            <template v-else-if="viewMode === 'recover'">
                <DialogHeader class="text-left space-y-2">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <LifeBuoy class="h-6 w-6" />
                    </div>
                    <DialogTitle class="text-xl font-bold tracking-tight">
                        Reset Master Password
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground leading-relaxed">
                        Enter your 24-character Emergency Recovery Key to decrypt your vault and choose a new Master Password. All your credentials remain safe.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleRecover" class="space-y-3.5 pt-2">
                    <div
                        v-if="errorMessage"
                        class="rounded-lg bg-destructive/10 p-3 text-xs text-destructive flex items-center gap-2"
                    >
                        <ShieldAlert class="h-4 w-4 shrink-0" />
                        <span>{{ errorMessage }}</span>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="recovery-key-input" class="text-xs font-medium">Your 24-Character Recovery Key</Label>
                        <Input
                            id="recovery-key-input"
                            v-model="recoveryKeyInput"
                            placeholder="XXXX-XXXX-XXXX-XXXX-XXXX-XXXX"
                            class="font-mono text-xs sm:text-sm uppercase tracking-wider h-10"
                            required
                            autofocus
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="recover-new-password" class="text-xs font-medium">New Master Password</Label>
                        <Input
                            id="recover-new-password"
                            v-model="masterPassword"
                            type="password"
                            placeholder="Choose new master password"
                            class="h-10 text-sm"
                            required
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="recover-confirm-password" class="text-xs font-medium">Confirm New Master Password</Label>
                        <Input
                            id="recover-confirm-password"
                            v-model="confirmPassword"
                            type="password"
                            placeholder="Confirm new master password"
                            class="h-10 text-sm"
                            required
                        />
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full sm:flex-1 h-10 text-xs"
                            @click="viewMode = 'unlock'; errorMessage = ''"
                        >
                            Back to Unlock
                        </Button>
                        <Button
                            type="submit"
                            class="w-full sm:flex-1 h-10 text-xs font-semibold"
                            :disabled="isProcessing"
                        >
                            <Spinner v-if="isProcessing" class="mr-1.5" />
                            Reset & Unlock
                        </Button>
                    </div>
                </form>
            </template>

            <!-- 3. WIPE CONFIRMATION MODE -->
            <template v-else-if="viewMode === 'wipe_confirm'">
                <DialogHeader class="text-left space-y-2">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-destructive/10 text-destructive"
                    >
                        <Trash2 class="h-6 w-6" />
                    </div>
                    <DialogTitle class="text-xl font-bold tracking-tight text-destructive">
                        Wipe & Reset Entire Vault
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground leading-relaxed">
                        Use this only if you have permanently lost both your Master Password and Recovery Key.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 pt-2">
                    <div class="rounded-xl border border-destructive/30 bg-destructive/10 p-3.5 text-xs text-destructive space-y-1.5 leading-relaxed">
                        <p class="font-bold flex items-center gap-1.5">
                            <AlertTriangle class="h-4 w-4 shrink-0" />
                            Permanent Data Loss
                        </p>
                        <p>This action will delete all existing encrypted vault items and reset your vault keys. You can then choose a fresh Master Password, but past items cannot be recovered.</p>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row gap-2 pt-1">
                        <Button
                            type="button"
                            variant="outline"
                            class="w-full sm:flex-1 h-10 text-xs"
                            @click="viewMode = 'unlock'"
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            class="w-full sm:flex-1 h-10 text-xs font-semibold"
                            :disabled="isProcessing"
                            @click="handleWipeVault"
                        >
                            <Spinner v-if="isProcessing" class="mr-1.5" />
                            Yes, Wipe and Reset
                        </Button>
                    </div>
                </div>
            </template>

            <!-- 4. SETUP MODE (INITIAL VAULT CREATION) -->
            <template v-else-if="!isConfigured || viewMode === 'setup'">
                <DialogHeader class="text-left space-y-2">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <Sparkles class="h-6 w-6" />
                    </div>
                    <DialogTitle class="text-xl font-bold tracking-tight">
                        Initialize Your Secure Vault
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground leading-relaxed">
                        End-to-end zero-knowledge encryption with AES-256-GCM + PBKDF2. Your Master Password encrypts your data locally in your browser.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleSetup" class="space-y-3.5 pt-2">
                    <div
                        v-if="errorMessage"
                        class="rounded-lg bg-destructive/10 p-3 text-xs text-destructive flex items-center gap-2"
                    >
                        <ShieldAlert class="h-4 w-4 shrink-0" />
                        <span>{{ errorMessage }}</span>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="setup-master-password" class="text-xs font-medium">Create Master Password</Label>
                        <div class="relative">
                            <Input
                                id="setup-master-password"
                                v-model="masterPassword"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Choose a strong master passphrase"
                                autocomplete="new-password"
                                class="h-10 pr-10 text-sm"
                                required
                            />
                            <button
                                type="button"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-muted-foreground hover:text-foreground cursor-pointer border-0 bg-transparent"
                                @click="showPassword = !showPassword"
                            >
                                <EyeOff v-if="showPassword" class="h-4 w-4" />
                                <Eye v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="setup-confirm-password" class="text-xs font-medium">Confirm Master Password</Label>
                        <Input
                            id="setup-confirm-password"
                            v-model="confirmPassword"
                            type="password"
                            placeholder="Confirm your master passphrase"
                            autocomplete="new-password"
                            class="h-10 text-sm"
                            required
                        />
                    </div>

                    <div class="rounded-lg border border-primary/20 bg-primary/5 p-3 text-xs text-muted-foreground space-y-1">
                        <p class="font-medium text-foreground flex items-center gap-1.5">
                            <ShieldCheck class="h-4 w-4 text-emerald-500 shrink-0" />
                            Emergency Recovery Key Included
                        </p>
                        <p>In the next step, a 24-character Emergency Key will be generated so you can always reset your password without losing any vault credentials.</p>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="h-10 px-4 text-xs font-medium cursor-pointer"
                            @click="emit('update:open', false)"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            class="flex-1 h-10 font-semibold"
                            :disabled="isProcessing"
                        >
                            <Spinner v-if="isProcessing" class="mr-2" />
                            Generate Keys & Setup Vault
                        </Button>
                    </div>
                </form>
            </template>

            <!-- 5. UNLOCK MODE -->
            <template v-else>
                <DialogHeader class="text-left space-y-2">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <Lock class="h-6 w-6" />
                    </div>
                    <DialogTitle class="text-xl font-bold tracking-tight text-foreground">
                        Unlock {{ isTeamVault ? teamName : 'Your Personal' }} Vault
                    </DialogTitle>
                    <DialogDescription class="text-xs text-muted-foreground leading-relaxed">
                        Enter your Master Password to decrypt your credentials for this session.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleUnlock" class="space-y-4 pt-2">
                    <div
                        v-if="errorMessage"
                        class="rounded-lg bg-destructive/10 p-3 text-xs text-destructive flex items-center gap-2"
                    >
                        <ShieldAlert class="h-4 w-4 shrink-0" />
                        <span>{{ errorMessage }}</span>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <Label for="unlock-master-password" class="text-xs font-medium text-foreground">
                                Master Password
                            </Label>
                            <button
                                type="button"
                                class="text-xs text-primary hover:underline font-medium cursor-pointer border-0 bg-transparent p-0 focus:outline-hidden"
                                @click="viewMode = 'recover'; errorMessage = ''"
                            >
                                Forgot Master Password?
                            </button>
                        </div>
                        <div class="relative">
                            <Input
                                id="unlock-master-password"
                                v-model="masterPassword"
                                type="password"
                                placeholder="Enter master password"
                                autocomplete="current-password"
                                class="h-11 pr-10 text-sm bg-background"
                                autofocus
                                required
                            />
                            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-muted-foreground">
                                <KeyRound class="h-4 w-4" />
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 pt-2">
                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="h-11 px-4 text-xs font-medium cursor-pointer"
                                @click="emit('update:open', false)"
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                class="flex-1 h-11 text-sm font-semibold shadow-xs"
                                :disabled="isProcessing"
                            >
                                <Spinner v-if="isProcessing" class="mr-2" />
                                Unlock Vault
                            </Button>
                        </div>

                        <div class="flex items-center justify-between border-t border-border/50 pt-2.5 text-xs text-muted-foreground">
                            <span>Lost access completely?</span>
                            <button
                                type="button"
                                class="font-medium text-destructive hover:underline cursor-pointer border-0 bg-transparent p-0 focus:outline-hidden"
                                @click="viewMode = 'wipe_confirm'"
                            >
                                Wipe & Reset Vault
                            </button>
                        </div>
                    </div>
                </form>
            </template>
        </DialogContent>
    </Dialog>
</template>
