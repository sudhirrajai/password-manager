<script setup lang="ts">
import {
    AlertTriangle,
    Check,
    Copy,
    Download,
    KeyRound,
    LifeBuoy,
    RefreshCw,
    Shield,
    ShieldAlert,
    ShieldCheck,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useVault } from '@/composables/useVault';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
}>();

const { hasRecoveryKey, rotateRecoveryKey, isUnlocked } = useVault();

const activeRecoveryKey = ref<string>('');
const isGenerating = ref(false);
const isCopied = ref(false);
const confirmRotate = ref(false);

watch(
    () => props.open,
    (val) => {
        if (val) {
            confirmRotate.value = false;
            isCopied.value = false;
        }
    },
);

async function handleGenerateOrRotate() {
    isGenerating.value = true;
    try {
        const res = await rotateRecoveryKey();
        if (res.success && res.recoveryKey) {
            activeRecoveryKey.value = res.recoveryKey;
            confirmRotate.value = false;
            toast.success('Generated fresh Emergency Recovery Key!');
            // Auto download on creation/rotation for maximum user safety
            downloadKit(res.recoveryKey);
        }
    } finally {
        isGenerating.value = false;
    }
}

function handleCopy(keyText: string) {
    if (!keyText) return;
    navigator.clipboard.writeText(keyText);
    isCopied.value = true;
    toast.success('Recovery Key copied to clipboard!');
    setTimeout(() => {
        isCopied.value = false;
    }, 2000);
}

function downloadKit(keyText?: string) {
    const key = keyText || activeRecoveryKey.value;
    if (!key) return;

    const content = `VAULTGUARD EMERGENCY RECOVERY KIT
==================================================
Created: ${new Date().toLocaleString()}
Emergency Recovery Key: ${key}

CRITICAL INSTRUCTIONS:
1. Store this file in a safe, secure offline location (e.g., printed on paper or stored on an encrypted USB drive).
2. UNIVERSAL KEY: This single Master Recovery Key works for ALL your vaults (Personal Vault and any Team Vaults).
3. If you ever forget your Master Password:
   a. Go to your Vault and click "Forgot Master Password?"
   b. Enter this 24-character code.
   c. Set a new Master Password.
4. All existing stored passwords, notes, cards, and server keys will remain 100% intact.
5. This recovery key is NEVER stored in plaintext on our servers.
==================================================`;

    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `vaultguard-emergency-kit-${new Date().toISOString().slice(0, 10)}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    toast.success('Emergency Recovery Kit (.txt) downloaded!');
}
</script>

<template>
    <Dialog :open="open" @update:open="(val) => emit('update:open', val)">
        <DialogContent
            class="w-full max-w-[calc(100%-2rem)] sm:max-w-lg rounded-2xl border border-border bg-card p-6 shadow-xl overflow-hidden"
            :showCloseButton="true"
        >
            <DialogHeader class="text-left space-y-2">
                <div class="flex items-center gap-2">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500"
                    >
                        <LifeBuoy class="h-5 w-5" />
                    </div>
                    <div>
                        <DialogTitle class="text-lg font-bold tracking-tight text-foreground">
                            Emergency Recovery Kit
                        </DialogTitle>
                        <p class="text-xs text-muted-foreground">
                            Universal reset key for all your vaults
                        </p>
                    </div>
                </div>
            </DialogHeader>

            <div class="space-y-4 pt-2">
                <!-- Common key alert banner -->
                <div
                    class="rounded-xl border border-primary/20 bg-primary/5 p-3.5 text-xs text-muted-foreground space-y-1.5 leading-relaxed"
                >
                    <div class="flex items-center gap-2 font-semibold text-foreground">
                        <ShieldCheck class="h-4 w-4 text-emerald-500 shrink-0" />
                        <span>Common Master Key for All Vaults</span>
                    </div>
                    <p>
                        This single emergency code protects your <strong>Personal Vault</strong> and <strong>all Team Vaults</strong>. If you ever forget your Master Password, you will use this exact key to restore access to everything without losing data.
                    </p>
                </div>

                <!-- Active key display if freshly generated or existing -->
                <div v-if="activeRecoveryKey" class="space-y-3">
                    <div
                        class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-center space-y-1.5"
                    >
                        <span
                            class="text-[11px] uppercase tracking-wider font-semibold text-emerald-600 dark:text-emerald-400"
                        >
                            Your Active Emergency Recovery Key
                        </span>
                        <div
                            class="font-mono text-base sm:text-xl font-bold tracking-wider text-foreground select-all break-all py-1"
                        >
                            {{ activeRecoveryKey }}
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="gap-1.5 w-full text-xs"
                            @click="handleCopy(activeRecoveryKey)"
                        >
                            <Check v-if="isCopied" class="h-3.5 w-3.5 text-emerald-500" />
                            <Copy v-else class="h-3.5 w-3.5" />
                            <span>{{ isCopied ? 'Copied!' : 'Copy Code' }}</span>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="gap-1.5 w-full text-xs border-primary/30 text-primary hover:bg-primary/10"
                            @click="downloadKit(activeRecoveryKey)"
                        >
                            <Download class="h-3.5 w-3.5" />
                            <span>Download .txt Kit</span>
                        </Button>
                    </div>
                </div>

                <!-- Key Status information when already configured -->
                <div v-else-if="hasRecoveryKey" class="space-y-3">
                    <div
                        class="rounded-xl border border-border/80 bg-muted/20 p-4 text-xs space-y-2"
                    >
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-foreground">Recovery Key Status:</span>
                            <Badge variant="outline" class="gap-1 text-emerald-600 border-emerald-500/30 bg-emerald-500/10">
                                <ShieldCheck class="h-3 w-3" />
                                Active & Armed
                            </Badge>
                        </div>
                        <p class="text-muted-foreground leading-relaxed">
                            For security, zero-knowledge recovery keys are only rendered on-screen when generated. If you misplaced your saved Emergency Kit file or need a new one, you can safely generate a fresh key below.
                        </p>
                    </div>

                    <div v-if="!confirmRotate">
                        <Button
                            type="button"
                            class="w-full gap-2 text-xs h-10 font-semibold"
                            @click="confirmRotate = true"
                        >
                            <RefreshCw class="h-3.5 w-3.5" />
                            <span>Generate & Download Fresh Emergency Kit</span>
                        </Button>
                    </div>

                    <div v-else class="space-y-2 rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs text-amber-700 dark:text-amber-300">
                        <p class="font-semibold flex items-center gap-1.5">
                            <AlertTriangle class="h-4 w-4 shrink-0" />
                            Replace Existing Recovery Key?
                        </p>
                        <p>Generating a new key will immediately invalidate any previous recovery keys you may have downloaded. All your passwords will stay intact.</p>
                        <div class="flex gap-2 pt-1">
                            <Button
                                size="sm"
                                variant="outline"
                                class="flex-1 h-8 text-xs"
                                @click="confirmRotate = false"
                            >
                                Cancel
                            </Button>
                            <Button
                                size="sm"
                                class="flex-1 h-8 text-xs font-semibold"
                                :disabled="isGenerating"
                                @click="handleGenerateOrRotate"
                            >
                                <Spinner v-if="isGenerating" class="mr-1.5" />
                                Yes, Generate New Key
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Not yet configured -->
                <div v-else class="space-y-3">
                    <div
                        class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 text-xs text-amber-700 dark:text-amber-300 space-y-1.5"
                    >
                        <p class="font-bold flex items-center gap-1.5">
                            <AlertTriangle class="h-4 w-4 shrink-0" />
                            No Recovery Key Detected
                        </p>
                        <p>Your vault was set up without an Emergency Recovery Key. Generate one now to protect yourself against accidental lockout.</p>
                    </div>

                    <Button
                        type="button"
                        class="w-full gap-2 text-xs h-10 font-semibold"
                        :disabled="isGenerating"
                        @click="handleGenerateOrRotate"
                    >
                        <Spinner v-if="isGenerating" class="mr-1.5" />
                        <KeyRound class="h-3.5 w-3.5" />
                        <span>Generate Emergency Recovery Key</span>
                    </Button>
                </div>

                <!-- Instructions footer -->
                <div class="rounded-lg border border-border/50 bg-muted/10 p-3 text-[11px] text-muted-foreground space-y-1">
                    <p class="font-semibold text-foreground">How to reset with this key:</p>
                    <ol class="list-decimal pl-4 space-y-0.5">
                        <li>Whenever your vault is locked, click <strong>"Forgot Master Password?"</strong></li>
                        <li>Enter your 24-character Recovery Key.</li>
                        <li>Choose a new Master Password. Access is restored instantly.</li>
                    </ol>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
