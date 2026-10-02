<script setup lang="ts">
import {
    Check,
    Clock,
    Copy,
    CreditCard,
    ExternalLink,
    Eye,
    EyeOff,
    FileText,
    Key,
    Pencil,
    Send,
    Server,
    Shield,
    ShieldAlert,
    Star,
    Trash2,
    Undo2,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    type DecryptedPayload,
    useVault,
    type VaultItemData,
} from '@/composables/useVault';
import { calculatePasswordStrength, generateTotpCode } from '@/lib/crypto';

const props = defineProps<{
    item: VaultItemData;
    isTrash?: boolean;
}>();

const emit = defineEmits<{
    (e: 'edit', item: VaultItemData): void;
    (e: 'delete', item: VaultItemData): void;
    (e: 'restore', item: VaultItemData): void;
    (e: 'forceDelete', item: VaultItemData): void;
    (e: 'share', item: VaultItemData): void;
    (e: 'favoriteToggle', item: VaultItemData): void;
}>();

const { copyToClipboardWithAutoClear, auditAction } = useVault();

const showPassword = ref(false);
const showCvv = ref(false);
const showPin = ref(false);
const showPrivateKey = ref(false);

const totpCode = ref<string>('------');
const totpRemaining = ref<number>(30);
let totpTimer: number | null = null;

const decrypted = computed<DecryptedPayload>(
    () => props.item.decrypted || { title: props.item.title },
);

const strength = computed(() => {
    return decrypted.value.password
        ? calculatePasswordStrength(decrypted.value.password)
        : null;
});

async function updateTotp() {
    if (!decrypted.value.totp) return;
    const res = await generateTotpCode(decrypted.value.totp);
    totpCode.value = res.code;
    totpRemaining.value = res.remainingSeconds;
}

watch(
    () => decrypted.value.totp,
    (secret) => {
        if (secret) {
            updateTotp();
            if (!totpTimer) {
                totpTimer = window.setInterval(updateTotp, 1000);
            }
        } else {
            if (totpTimer) {
                window.clearInterval(totpTimer);
                totpTimer = null;
            }
            totpCode.value = '------';
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (decrypted.value.totp && !totpTimer) {
        updateTotp();
        totpTimer = window.setInterval(updateTotp, 1000);
    }
});

onUnmounted(() => {
    if (totpTimer) {
        window.clearInterval(totpTimer);
        totpTimer = null;
    }
});

async function copyField(text: string, label: string) {
    if (!text) return;
    await copyToClipboardWithAutoClear(text, label);
    await auditAction(
        `copied_${label.toLowerCase().replace(/\s+/g, '_')}`,
        props.item.id,
        props.item.title,
    );
}
</script>

<template>
    <div
        class="flex h-full flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-xs"
    >
        <!-- Header -->
        <div
            class="flex flex-col justify-between gap-4 border-b border-border/60 bg-muted/20 p-6 sm:flex-row sm:items-center"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl shadow-xs"
                    :class="[
                        item.type === 'login'
                            ? 'bg-blue-500/10 text-blue-500'
                            : item.type === 'card'
                              ? 'bg-amber-500/10 text-amber-500'
                              : item.type === 'note'
                                ? 'bg-emerald-500/10 text-emerald-500'
                                : 'bg-purple-500/10 text-purple-500',
                    ]"
                >
                    <Key v-if="item.type === 'login'" class="h-6 w-6" />
                    <CreditCard
                        v-else-if="item.type === 'card'"
                        class="h-6 w-6"
                    />
                    <FileText
                        v-else-if="item.type === 'note'"
                        class="h-6 w-6"
                    />
                    <Server v-else class="h-6 w-6" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h2
                            class="text-xl font-bold tracking-tight text-foreground"
                        >
                            {{ decrypted.title || item.title }}
                        </h2>
                        <button
                            v-if="!isTrash"
                            type="button"
                            class="p-1 text-muted-foreground transition-colors hover:text-amber-500"
                            :class="{ 'text-amber-500': item.is_favorite }"
                            @click="emit('favoriteToggle', item)"
                            title="Favorite"
                        >
                            <Star
                                class="h-4 w-4"
                                :class="{ 'fill-amber-500': item.is_favorite }"
                            />
                        </button>
                    </div>
                    <div
                        class="mt-0.5 flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Badge
                            variant="outline"
                            class="py-0 text-[11px] font-medium capitalize"
                        >
                            {{ item.type }}
                        </Badge>
                        <span
                            v-if="item.folder"
                            class="rounded bg-muted px-2 py-0.5 text-[11px]"
                        >
                            {{ item.folder }}
                        </span>
                        <span>• Added by {{ item.user?.name || 'You' }}</span>
                    </div>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-2">
                <template v-if="!isTrash">
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-1.5"
                        @click="emit('share', item)"
                    >
                        <Send class="h-3.5 w-3.5" />
                        <span>Share Secret</span>
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-1.5"
                        @click="emit('edit', item)"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                        <span>Edit</span>
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="text-destructive hover:bg-destructive/10"
                        @click="emit('delete', item)"
                        title="Move to Trash"
                    >
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </template>
                <template v-else>
                    <Button
                        variant="outline"
                        size="sm"
                        class="gap-1.5 text-emerald-600"
                        @click="emit('restore', item)"
                    >
                        <Undo2 class="h-3.5 w-3.5" />
                        <span>Restore</span>
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        class="gap-1.5"
                        @click="emit('forceDelete', item)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        <span>Delete Permanently</span>
                    </Button>
                </template>
            </div>
        </div>

        <!-- Content Body -->
        <div class="flex-1 space-y-6 overflow-y-auto p-6">
            <!-- LOGIN VIEW -->
            <template v-if="item.type === 'login'">
                <!-- Username -->
                <div
                    v-if="decrypted.username"
                    class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/10 p-3"
                >
                    <div class="space-y-0.5">
                        <span class="text-xs font-medium text-muted-foreground"
                            >Username / Email</span
                        >
                        <p class="font-mono text-sm text-foreground select-all">
                            {{ decrypted.username }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        @click="copyField(decrypted.username!, 'Username')"
                    >
                        <Copy class="h-4 w-4" />
                    </Button>
                </div>

                <!-- Password -->
                <div v-if="decrypted.password" class="space-y-2">
                    <div
                        class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/10 p-3"
                    >
                        <div class="flex-1 space-y-0.5 pr-2">
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >Password</span
                            >
                            <p
                                class="font-mono text-sm text-foreground select-all"
                            >
                                {{
                                    showPassword
                                        ? decrypted.password
                                        : '••••••••••••••••'
                                }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8"
                                @click="showPassword = !showPassword"
                            >
                                <EyeOff v-if="showPassword" class="h-4 w-4" />
                                <Eye v-else class="h-4 w-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8"
                                @click="
                                    copyField(decrypted.password!, 'Password')
                                "
                            >
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <!-- Password strength & audit badge -->
                    <div
                        v-if="strength"
                        class="flex items-center justify-between px-1 text-xs"
                    >
                        <div
                            class="flex items-center gap-1.5"
                            :class="
                                strength.score >= 3
                                    ? 'text-emerald-500'
                                    : 'text-amber-500'
                            "
                        >
                            <Shield class="h-3.5 w-3.5" />
                            <span
                                >Password Strength:
                                <strong>{{ strength.label }}</strong> ({{
                                    strength.entropy
                                }}
                                bits)</span
                            >
                        </div>
                        <span class="text-[11px] text-muted-foreground"
                            >Crack time: ~{{ strength.crackTime }}</span
                        >
                    </div>
                </div>

                <!-- Live TOTP Authenticator -->
                <div
                    v-if="decrypted.totp"
                    class="space-y-3 rounded-xl border border-primary/20 bg-primary/5 p-4"
                >
                    <div class="flex items-center justify-between">
                        <span
                            class="flex items-center gap-1.5 text-xs font-semibold tracking-wider text-primary uppercase"
                        >
                            <Clock class="animate-spin-slow h-4 w-4" />
                            Two-Factor Authenticator (TOTP)
                        </span>
                        <span
                            class="font-mono text-xs font-medium text-muted-foreground"
                        >
                            Refreshes in {{ totpRemaining }}s
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <div
                            class="font-mono text-3xl font-extrabold tracking-widest text-foreground select-all"
                        >
                            {{ totpCode.slice(0, 3) }} {{ totpCode.slice(3) }}
                        </div>
                        <Button
                            size="sm"
                            class="gap-1.5"
                            @click="copyField(totpCode, 'TOTP Code')"
                        >
                            <Copy class="h-3.5 w-3.5" />
                            <span>Copy Code</span>
                        </Button>
                    </div>

                    <!-- Progress bar -->
                    <div
                        class="h-1.5 w-full overflow-hidden rounded-full bg-primary/20"
                    >
                        <div
                            class="h-full rounded-full bg-primary transition-all duration-1000 ease-linear"
                            :style="{ width: `${(totpRemaining / 30) * 100}%` }"
                        />
                    </div>
                </div>

                <!-- Website URL -->
                <div
                    v-if="decrypted.url"
                    class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/10 p-3"
                >
                    <div class="flex-1 space-y-0.5 overflow-hidden pr-2">
                        <span class="text-xs font-medium text-muted-foreground"
                            >Website</span
                        >
                        <a
                            :href="decrypted.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="block truncate text-sm text-primary hover:underline"
                        >
                            {{ decrypted.url }}
                        </a>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            as-child
                        >
                            <a
                                :href="decrypted.url"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLink class="h-4 w-4" />
                            </a>
                        </Button>
                    </div>
                </div>
            </template>

            <!-- CARD VIEW -->
            <template v-else-if="item.type === 'card'">
                <div
                    class="space-y-4 rounded-xl border border-border bg-gradient-to-br from-card via-muted/30 to-muted/10 p-5 shadow-sm"
                >
                    <div
                        class="flex items-center justify-between font-mono text-xs text-muted-foreground"
                    >
                        <span>PAYMENT CARD</span>
                        <CreditCard class="h-5 w-5 text-foreground" />
                    </div>

                    <div class="space-y-1">
                        <span class="font-mono text-xs text-muted-foreground"
                            >CARD NUMBER</span
                        >
                        <div class="flex items-center justify-between">
                            <p
                                class="font-mono text-xl font-bold tracking-widest text-foreground select-all"
                            >
                                {{
                                    decrypted.cardNumber ||
                                    '•••• •••• •••• ••••'
                                }}
                            </p>
                            <Button
                                v-if="decrypted.cardNumber"
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8"
                                @click="
                                    copyField(
                                        decrypted.cardNumber!,
                                        'Card Number',
                                    )
                                "
                            >
                                <Copy class="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 pt-2">
                        <div>
                            <span
                                class="font-mono text-[10px] text-muted-foreground"
                                >CARDHOLDER</span
                            >
                            <p
                                class="text-sm font-semibold tracking-wider text-foreground uppercase select-all"
                            >
                                {{ decrypted.cardholder || 'NOT SPECIFIED' }}
                            </p>
                        </div>
                        <div>
                            <span
                                class="font-mono text-[10px] text-muted-foreground"
                                >EXPIRES</span
                            >
                            <p
                                class="font-mono text-sm font-semibold text-foreground"
                            >
                                {{ decrypted.expMonth || '--' }}/{{
                                    decrypted.expYear || '--'
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div
                        v-if="decrypted.cvv"
                        class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/10 p-3"
                    >
                        <div>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >CVV</span
                            >
                            <p class="font-mono text-sm text-foreground">
                                {{ showCvv ? decrypted.cvv : '•••' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7"
                                @click="showCvv = !showCvv"
                            >
                                <EyeOff v-if="showCvv" class="h-3.5 w-3.5" />
                                <Eye v-else class="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7"
                                @click="copyField(decrypted.cvv!, 'CVV')"
                            >
                                <Copy class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>

                    <div
                        v-if="decrypted.pin"
                        class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/10 p-3"
                    >
                        <div>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >Card PIN</span
                            >
                            <p class="font-mono text-sm text-foreground">
                                {{ showPin ? decrypted.pin : '••••' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7"
                                @click="showPin = !showPin"
                            >
                                <EyeOff v-if="showPin" class="h-3.5 w-3.5" />
                                <Eye v-else class="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7"
                                @click="copyField(decrypted.pin!, 'PIN')"
                            >
                                <Copy class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- NOTE VIEW -->
            <template v-else-if="item.type === 'note'">
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >Note Content</span
                        >
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-7 gap-1 text-xs"
                            @click="
                                copyField(decrypted.notes || '', 'Note Content')
                            "
                        >
                            <Copy class="h-3.5 w-3.5" />
                            <span>Copy All</span>
                        </Button>
                    </div>
                    <div
                        class="rounded-xl border border-border/60 bg-muted/20 p-4 font-mono text-sm leading-relaxed whitespace-pre-wrap text-foreground select-all"
                    >
                        {{ decrypted.notes || 'No content.' }}
                    </div>
                </div>
            </template>

            <!-- SERVER VIEW -->
            <template v-else-if="item.type === 'server'">
                <div class="grid grid-cols-3 gap-3">
                    <div
                        v-if="decrypted.host"
                        class="col-span-2 flex items-center justify-between rounded-lg border border-border/50 bg-muted/10 p-3"
                    >
                        <div>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >Host</span
                            >
                            <p
                                class="font-mono text-sm text-foreground select-all"
                            >
                                {{ decrypted.host }}
                            </p>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-8 w-8"
                            @click="copyField(decrypted.host!, 'Host')"
                        >
                            <Copy class="h-4 w-4" />
                        </Button>
                    </div>

                    <div
                        v-if="decrypted.port"
                        class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/10 p-3"
                    >
                        <div>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >Port</span
                            >
                            <p class="font-mono text-sm text-foreground">
                                {{ decrypted.port }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    v-if="decrypted.username"
                    class="flex items-center justify-between rounded-lg border border-border/50 bg-muted/10 p-3"
                >
                    <div>
                        <span class="text-xs font-medium text-muted-foreground"
                            >Username</span
                        >
                        <p class="font-mono text-sm text-foreground select-all">
                            {{ decrypted.username }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8"
                        @click="copyField(decrypted.username!, 'Username')"
                    >
                        <Copy class="h-4 w-4" />
                    </Button>
                </div>

                <div v-if="decrypted.privateKey" class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted-foreground"
                            >Private Key / Secret</span
                        >
                        <div class="flex items-center gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7"
                                @click="showPrivateKey = !showPrivateKey"
                            >
                                <EyeOff
                                    v-if="showPrivateKey"
                                    class="h-3.5 w-3.5"
                                />
                                <Eye v-else class="h-3.5 w-3.5" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-7 w-7"
                                @click="
                                    copyField(
                                        decrypted.privateKey!,
                                        'Private Key',
                                    )
                                "
                            >
                                <Copy class="h-3.5 w-3.5" />
                            </Button>
                        </div>
                    </div>
                    <div
                        class="max-h-48 overflow-x-auto rounded-lg border border-border/60 bg-muted/20 p-3 font-mono text-xs whitespace-pre text-foreground select-all"
                    >
                        {{
                            showPrivateKey
                                ? decrypted.privateKey
                                : '••••••••••••••••••••••••••••••••'
                        }}
                    </div>
                </div>
            </template>

            <!-- Additional Notes for non-note types -->
            <div
                v-if="item.type !== 'note' && decrypted.notes"
                class="space-y-1.5 border-t border-border/40 pt-4"
            >
                <span
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >Notes</span
                >
                <p
                    class="rounded-lg border border-border/40 bg-muted/20 p-3 text-sm leading-relaxed whitespace-pre-wrap text-foreground select-all"
                >
                    {{ decrypted.notes }}
                </p>
            </div>
        </div>
    </div>
</template>
